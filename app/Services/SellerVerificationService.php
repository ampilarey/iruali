<?php

namespace App\Services;

use App\Models\SellerVerification;
use App\Models\User;
use App\Notifications\BusinessVerificationReviewed;
use App\Support\Audit;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Business verification. A shop sends its business registration certificate and number and its
 * owner's national ID card number and card photo (when applying, or in Seller Centre → Settings);
 * iruali approves or rejects them with a reason (Admin → Verifications) and the shop is emailed
 * either way. Approved shops show "Verified business"; sending anything new puts the shop back to
 * pending until it has been checked again.
 */
class SellerVerificationService
{
    use SecureFileUpload;

    /** Largest file accepted, in KB (5 MB) */
    public const MAX_KB = 5120;

    /** Content types accepted for each document, with the extension the stored file gets */
    public const CERTIFICATE_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public const ID_CARD_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** Form fields => [document, accepted types] */
    protected const UPLOADS = [
        'registration_certificate' => ['certificate', self::CERTIFICATE_TYPES],
        'id_card_front' => ['id_card', self::ID_CARD_TYPES],
    ];

    /**
     * Validation rules. When the shop already has documents on file, the files and the ID number may
     * be left empty to keep what is there.
     *
     * @return array<string, mixed>
     */
    public function rules(?SellerVerification $existing = null): array
    {
        $required = $existing ? 'nullable' : 'required';

        return [
            'business_registration_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/. -]*$/'],
            'national_id_number' => [$required, 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9 -]*$/'],
            'registration_certificate' => [$required, 'file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::MAX_KB],
            'id_card_front' => [$required, 'file', 'mimes:jpg,jpeg,png', 'max:'.self::MAX_KB],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'business_registration_number.regex' => __('Enter the number as it is on your certificate, for example C-0123/2020.'),
            'national_id_number.regex' => __('Enter the number as it is on the ID card, for example A123456.'),
            'registration_certificate.mimes' => __('The registration certificate must be a PDF, JPG or PNG file.'),
            'registration_certificate.max' => __('The registration certificate must be 5 MB or smaller.'),
            'id_card_front.mimes' => __('The ID card photo must be a JPG or PNG image.'),
            'id_card_front.max' => __('The ID card photo must be 5 MB or smaller.'),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'business_registration_number' => __('business registration number'),
            'national_id_number' => __('national ID card number'),
            'registration_certificate' => __('registration certificate'),
            'id_card_front' => __('ID card photo'),
        ];
    }

    /**
     * The optional "Business verification" part of the seller application: null when the applicant
     * left all of it empty, otherwise every field is required.
     *
     * @return array<string, mixed>|null
     */
    public function validateApplication(Request $request): ?array
    {
        $given = collect(array_keys($this->rules()))->contains(fn (string $field) => $request->hasFile($field) || filled($request->input($field)));

        return $given ? $request->validate($this->rules(), $this->messages(), $this->attributes()) : null;
    }

    /**
     * Save what the shop sent. New files replace the old ones (which are then deleted), and any
     * change puts the shop back to pending for iruali to check, hiding the badge until then.
     * Null when nothing changed.
     *
     * @param  array<string, mixed>  $data  validated: business_registration_number, national_id_number, registration_certificate, id_card_front
     */
    public function submit(User $shop, array $data): ?SellerVerification
    {
        $verification = $shop->businessVerification()->first() ?? new SellerVerification(['user_id' => $shop->id]);
        $previousStatus = $verification->exists ? $verification->status : null;

        $verification->business_registration_number = self::normalise((string) $data['business_registration_number']);
        // Left empty, the number on file stays; compared in plain text, as each encryption differs
        $nationalId = filled($data['national_id_number'] ?? null) ? self::normalise((string) $data['national_id_number']) : null;
        if ($nationalId !== null && $nationalId !== $verification->nationalIdNumber()) {
            $verification->national_id_number = $nationalId;
        }

        // New files are stored first; the old ones go only once the record points at the new ones
        $stored = [];
        $replaced = [];
        foreach (self::UPLOADS as $field => [$document, $types]) {
            $file = $data[$field] ?? null;
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $this->storePrivateFileSecurely($file, 'seller-verifications/'.$shop->id, $types, self::MAX_KB, SellerVerification::DISK);
            if ($path === null) {
                $this->deleteFiles($stored);
                throw ValidationException::withMessages([$field => __('This file could not be saved. Upload a PDF, JPG or PNG of 5 MB or less.')]);
            }
            $column = SellerVerification::DOCUMENTS[$document];
            if ($verification->getAttribute($column)) {
                $replaced[$document] = $verification->getAttribute($column);
            }
            $verification->setAttribute($column, $path);
            $stored[] = $path;
        }

        if ($verification->exists && ! $verification->isDirty()) {
            return null;
        }

        $verification->forceFill([
            'status' => SellerVerification::PENDING,
            'submitted_at' => now(),
            'reviewed_at' => null,
            'reviewed_by' => null,
            'rejection_reason' => null,
        ]);

        try {
            $verification->save();
        } catch (Throwable $e) {
            $this->deleteFiles($stored);
            throw $e;
        }

        $this->deleteFiles(array_values($replaced));
        $shop->setRelation('businessVerification', $verification);

        Audit::record('seller.verification_submitted', $shop, array_filter([
            'shop' => $shop->shopName(),
            'registration_number' => $verification->business_registration_number,
            'previous_status' => $previousStatus,
            'replaced' => array_keys($replaced) ?: null,
        ]));

        return $verification;
    }

    /**
     * iruali has checked the documents: the shop shows "Verified business" and is emailed.
     * False when it was already approved.
     */
    public function approve(SellerVerification $verification, User $admin): bool
    {
        if ($verification->isApproved()) {
            return false;
        }

        $verification->forceFill([
            'status' => SellerVerification::APPROVED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'rejection_reason' => null,
        ])->save();

        Audit::record('seller.verification_approved', $verification->user, [
            'shop' => $verification->user?->shopName(),
            'registration_number' => $verification->business_registration_number,
        ]);
        $this->notifyShop($verification);

        return true;
    }

    /**
     * The documents don't check out (or a verification is withdrawn): the badge goes and the shop is
     * emailed the reason. False when it was already rejected.
     */
    public function reject(SellerVerification $verification, User $admin, string $reason): bool
    {
        if ($verification->isRejected()) {
            return false;
        }

        $previousStatus = $verification->status;
        $verification->forceFill([
            'status' => SellerVerification::REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $admin->id,
            'rejection_reason' => trim($reason),
        ])->save();

        Audit::record('seller.verification_rejected', $verification->user, [
            'shop' => $verification->user?->shopName(),
            'reason' => $verification->rejection_reason,
            'previous_status' => $previousStatus,
        ]);
        $this->notifyShop($verification);

        return true;
    }

    /**
     * One of the documents, shown in the browser and never cached. The caller has already checked
     * that the viewer is iruali staff or the shop itself.
     */
    public function documentResponse(SellerVerification $verification, string $document): StreamedResponse
    {
        abort_unless($verification->documentExists($document), 404);

        $path = (string) $verification->documentPath($document);
        $name = ($document === 'certificate' ? 'registration-certificate' : 'id-card').'-'.$verification->user_id.'.'.pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk(SellerVerification::DISK)->response($path, $name, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** Upper case, single spaces: "c-0123/2020 " becomes "C-0123/2020". */
    public static function normalise(string $value): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', $value)));
    }

    protected function notifyShop(SellerVerification $verification): void
    {
        $shop = $verification->user;
        if (! $shop?->email) {
            return;
        }

        try {
            $shop->notify(new BusinessVerificationReviewed($verification->isApproved(), $verification->rejection_reason));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @param  array<int, string>  $paths */
    protected function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deletePrivateFile($path, SellerVerification::DISK);
        }
    }
}
