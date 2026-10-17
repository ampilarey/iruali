<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Models\SmsTypeSetting;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsResult;
use App\Services\Sms\SmsText;
use App\Support\Audit;
use App\Support\SmsTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → SMS, the control centre: all SMS on or off, a monthly limit, quiet hours, each kind of
 * message switched on or off and reworded (App\Support\SmsTypes), previews and test sends, and the
 * log (filters, CSV, resend).
 */
class SmsController extends Controller
{
    public const STATUSES = ['sent', 'logged', 'held', 'blocked', 'failed', 'invalid'];

    public function index(Request $request, SmsManager $sms)
    {
        $messages = $this->filtered($request)->latest('id')->paginate(50)->withQueryString();
        $month = SmsMessage::where('created_at', '>=', now()->startOfMonth())->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $sentByType = SmsMessage::where('created_at', '>=', now()->startOfMonth())->where('status', 'sent')->whereNotNull('type')
            ->selectRaw('type, count(*) as n')->groupBy('type')->pluck('n', 'type');

        return view('admin.sms.index', [
            'messages' => $messages,
            'month' => $month,
            'sentByType' => $sentByType,
            'driver' => $sms->driverName(),
            'live' => $sms->isLive(),
            'types' => SmsTypes::all(),
            'typeSettings' => SmsTypes::settings(),
            'settings' => [
                'enabled' => SmsManager::enabled(),
                'limit' => SmsManager::monthlyLimit(),
                'sentThisMonth' => SmsManager::sentThisMonth(),
                'quiet_from' => (string) Setting::get('sms_quiet_from'),
                'quiet_until' => (string) Setting::get('sms_quiet_until'),
                'quietNow' => SmsManager::quietUntil(),
            ],
            'filters' => $request->only(['status', 'type', 'q']),
            'canManage' => $request->user()->hasRole('admin'),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'sms_enabled' => 'required|boolean',
            'sms_monthly_limit' => 'required|integer|min:0|max:1000000',
            'sms_quiet_from' => ['nullable', 'date_format:H:i', 'required_with:sms_quiet_until'],
            'sms_quiet_until' => ['nullable', 'date_format:H:i', 'required_with:sms_quiet_from', 'different:sms_quiet_from'],
        ]);
        $data['sms_quiet_from'] ??= '';
        $data['sms_quiet_until'] ??= '';

        Setting::set($data);
        Audit::record('sms.settings_saved', null, $data);

        return redirect()->route('admin.sms')->with('success', $data['sms_enabled'] ? 'SMS settings saved.' : 'SMS settings saved. All SMS are switched off.');
    }

    public function updateType(Request $request, string $type)
    {
        $this->adminOnly($request);
        abort_unless(isset(SmsTypes::all()[$type]), 404);
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'template_en' => ['nullable', 'string', 'max:480', $this->placeholders($type)],
            'template_dv' => ['nullable', 'string', 'max:480', $this->placeholders($type)],
        ]);
        if (! $data['enabled'] && SmsTypes::urgent($type)) {
            return back()->withInput()->withErrors(['enabled' => 'Verification codes can\'t be switched off: people could not verify their phone. Use the switch for all SMS instead.']);
        }

        SmsTypeSetting::updateOrCreate(['key' => $type], [
            'enabled' => (bool) $data['enabled'],
            'template_en' => filled($data['template_en'] ?? null) ? trim($data['template_en']) : null,
            'template_dv' => filled($data['template_dv'] ?? null) ? trim($data['template_dv']) : null,
            'updated_by' => $request->user()->id,
        ]);
        Audit::record('sms.type_saved', null, ['type' => $type, 'enabled' => (bool) $data['enabled'], 'own_wording' => filled($data['template_en'] ?? null) || filled($data['template_dv'] ?? null)]);

        return redirect()->to(route('admin.sms').'#sms-type-'.$type)->with('success', '"'.SmsTypes::get($type)['label'].'" saved.');
    }

    /**
     * Send this message, with example values, to a number: the saved wording, in English or Dhivehi.
     */
    public function testType(Request $request, string $type, SmsManager $sms)
    {
        abort_unless(isset(SmsTypes::all()[$type]), 404);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30', fn ($attr, $value, $fail) => PhoneNumber::isValid($value) ?: $fail('Enter a Maldivian mobile number (7 digits starting with 7 or 9).')],
            'locale' => 'required|in:en,dv',
        ]);

        $example = SmsTypes::example($type, $data['locale']);
        if ($example->text === '') {
            return redirect()->to(route('admin.sms').'#sms-type-'.$type)->with('error', 'This message is switched off, so there is nothing to send.');
        }
        $result = $sms->send($data['to'], new SmsText($type, $example->text, true));

        return redirect()->to(route('admin.sms').'#sms-type-'.$type)->with($result->success ? 'success' : 'error', $this->resultLine($result));
    }

    public function test(Request $request, SmsManager $sms)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30', fn ($attr, $value, $fail) => PhoneNumber::isValid($value) ?: $fail('Enter a Maldivian mobile number (7 digits starting with 7 or 9).')],
            'message' => 'required|string|max:320',
        ]);

        $result = $sms->send($data['to'], $data['message'], 'test', urgent: true);

        return back()->with($result->success ? 'success' : 'error', $this->resultLine($result));
    }

    public function resend(SmsMessage $message, SmsManager $sms)
    {
        abort_unless(in_array($message->status, ['failed', 'blocked', 'held'], true), 422);
        $result = $sms->resend($message);
        if ($message->status === 'held') {
            $message->delete(); // sent now instead of when quiet hours end (the new row is in the log)
        }

        return back()->with($result->success ? 'success' : 'error', $this->resultLine($result));
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Type', 'To', 'Status', 'Message', 'Gateway reply', 'Cost']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $m) {
                    fputcsv($out, [$m->created_at?->format('Y-m-d H:i'), $m->type, $m->to, $m->status, $m->message, $m->provider_response, $m->cost]);
                }
            });
            fclose($out);
        }, 'iruali-sms-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Builder<SmsMessage>
     */
    protected function filtered(Request $request): Builder
    {
        $request->validate([
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'type' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:30'],
        ]);

        return SmsMessage::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $digits = preg_replace('/\D/', '', (string) $request->input('q'));
                $q->where('to', 'like', '%'.($digits !== '' ? substr($digits, -7) : '-').'%');
            });
    }

    /**
     * A custom wording may use only this message's placeholders, and must keep the required ones.
     */
    protected function placeholders(string $type): \Closure
    {
        $definition = SmsTypes::get($type);

        return function ($attribute, $value, $fail) use ($definition) {
            if (blank($value)) {
                return;
            }
            preg_match_all('/\{([a-z_]+)\}/', (string) $value, $m);
            $unknown = array_diff($m[1], array_keys($definition['data']));
            if ($unknown) {
                $fail('Unknown placeholder {'.implode('}, {', $unknown).'}. This message can use: {'.implode('}, {', array_keys($definition['data'])).'}.');

                return;
            }
            foreach ($definition['required'] ?? [] as $required) {
                if (! in_array($required, $m[1], true)) {
                    $fail('Keep {'.$required.'} in the wording: without it the message is useless.');
                }
            }
        };
    }

    protected function adminOnly(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }

    protected function resultLine(SmsResult $result): string
    {
        return match ($result->status) {
            'sent' => 'Message accepted by the gateway.',
            'logged' => 'SMS driver is "log": the message was written to the log and the list below, nothing was sent.',
            'held' => 'Quiet hours: the message is held and goes out when they end.',
            'blocked' => 'Not sent: '.$result->providerResponse,
            default => 'The gateway did not accept the message: '.($result->providerResponse ?: $result->status),
        };
    }
}
