<?php

declare(strict_types=1);

if (!defined('ABSPATH')) { exit; }

final class OptiGrid_Subscriptions_Payment_Result
{
    public string $gateway;
    public string $external_id;
    public string $status;
    public string $amount;
    public string $currency;
    public string $message;
    public string $processed_at;
    public array $raw;

    public function __construct(array $data)
    {
        $this->gateway = sanitize_key((string)($data['gateway'] ?? ''));
        $this->external_id = sanitize_text_field((string)($data['external_operation_id'] ?? ''));
        $this->status = sanitize_key((string)($data['status'] ?? 'error'));
        $this->amount = number_format((float)($data['amount'] ?? 0), 2, '.', '');
        $this->currency = strtoupper(sanitize_text_field((string)($data['currency'] ?? 'EUR')));
        $this->message = sanitize_textarea_field((string)($data['message'] ?? ''));
        $this->processed_at = sanitize_text_field((string)($data['processed_at'] ?? current_time('mysql', true)));
        $this->raw = is_array($data['raw'] ?? null) ? $data['raw'] : [];

        if ($this->gateway === '' || $this->external_id === '') {
            throw new InvalidArgumentException('La pasarela devolvió un resultado incompleto.');
        }
        if (!in_array($this->status, ['approved','rejected','pending','cancelled','error'], true)) {
            throw new InvalidArgumentException('Estado de pago no reconocido: ' . $this->status);
        }
    }

    public function event_type(): string { return 'payment.' . $this->status; }
    public function event_id(): string { return $this->external_id . ':' . $this->status; }
    public function to_payload(): array
    {
        return [
            'gateway' => $this->gateway,
            'external_operation_id' => $this->external_id,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'message' => $this->message,
            'processed_at' => $this->processed_at,
            'raw' => $this->raw,
        ];
    }
}
