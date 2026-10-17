<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsText;
use App\Services\Telegram;
use App\Support\Audit;
use App\Support\TeamAlerts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Team alerts: which events go to the Telegram group, and which each staff member gets by SMS.
 */
class TeamAlertsController extends Controller
{
    public function index()
    {
        return view('admin.alerts.index', [
            'events' => TeamAlerts::EVENTS,
            'staff' => TeamAlerts::staff(),
            'telegramConfigured' => Telegram::configured(),
            'chatId' => (string) Setting::get('telegram_chat_id'),
            'telegramEvents' => TeamAlerts::telegramEvents(),
            'chats' => session('telegram_chats', []),
        ]);
    }

    public function update(Request $request)
    {
        $events = array_keys(TeamAlerts::EVENTS);
        $data = $request->validate([
            'telegram_chat_id' => ['nullable', 'string', 'max:40', 'regex:/^-?\d+$|^@[A-Za-z0-9_]{5,}$/'],
            'telegram_events' => ['array'],
            'telegram_events.*' => [Rule::in($events)],
            'sms' => ['array'],
            'sms.*' => ['array'],
            'sms.*.*' => [Rule::in($events)],
        ], ['telegram_chat_id.regex' => 'The chat id is a number (groups start with -100…) or a public channel name like @iruali_orders.']);

        Setting::set([
            'telegram_chat_id' => trim((string) ($data['telegram_chat_id'] ?? '')),
            'telegram_events' => implode(',', $data['telegram_events'] ?? []),
        ]);
        foreach (TeamAlerts::staff() as $member) {
            $member->forceFill(['team_alerts' => array_values($data['sms'][$member->id] ?? [])])->save();
        }
        Audit::record('settings.team_alerts', null, ['telegram_events' => $data['telegram_events'] ?? [], 'sms' => $data['sms'] ?? []]);

        return redirect()->route('admin.alerts')->with('success', 'Team alerts saved.');
    }

    /**
     * A test message to the Telegram group, or by SMS to one staff member.
     */
    public function test(Request $request, Telegram $telegram, SmsManager $sms)
    {
        $data = $request->validate(['to' => ['required', 'string', 'max:20']]);
        $text = 'iruali: test alert from Admin → Team alerts. If you can read this, alerts work.';

        if ($data['to'] === 'telegram') {
            $chat = TeamAlerts::telegramChatId();
            if (! $chat) {
                return back()->with('error', 'Set TELEGRAM_BOT_TOKEN in .env and save the group\'s chat id first.');
            }
            $result = $telegram->send($chat, $text);

            return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Sent to the Telegram group.' : 'Telegram did not accept it: '.$result['error'].' (Is the bot a member of the group?)');
        }

        $member = TeamAlerts::staff()->firstWhere('id', (int) $data['to']);
        abort_unless($member instanceof User && filled($member->phone), 422);
        $result = $sms->send($member->phone, new SmsText('team_alert', $text, true));

        return back()->with($result->success ? 'success' : 'error', $result->success ? 'Test SMS sent to '.$member->name.' ('.$result->status.').' : 'Not sent: '.($result->providerResponse ?: $result->status));
    }

    /**
     * The chats that wrote to the bot lately, to pick the group's chat id.
     */
    public function findChats(Telegram $telegram)
    {
        $chats = $telegram->recentChats();

        return back()->with('telegram_chats', $chats)->with($chats ? 'success' : 'error', $chats
            ? 'Pick your group below.'
            : 'No chats found. Add the bot to your group, send a message in the group (e.g. "hello"), then try again.');
    }
}
