<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

/**
 * Composes a Gmail `q` search fragment from a `new_email_received` trigger
 * config. Only the free-form query and the structured filter fields that map
 * to Gmail's `q` parameter are handled here; `label_id` is passed to Gmail
 * as a separate `labelIds` argument and is intentionally NOT included.
 */
final class GmailTriggerQueryComposer
{
    /**
     * @param  array<string, mixed>  $config
     * @return string|null  Null when no filter is set.
     */
    public function compose(array $config): ?string
    {
        $tokens = [];

        $from = $this->readString($config, 'from');
        if ($from !== null) {
            $tokens[] = 'from:'.$this->quoteIfNeeded($from);
        }

        $subject = $this->readString($config, 'subject');
        if ($subject !== null) {
            $tokens[] = 'subject:'.$this->quoteIfNeeded($subject);
        }

        if (($config['has_attachment'] ?? null) === true) {
            $tokens[] = 'has:attachment';
        }

        $query = $this->readString($config, 'query');
        if ($query !== null) {
            $tokens[] = $query;
        }

        return $tokens === [] ? null : implode(' ', $tokens);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function readString(array $config, string $key): ?string
    {
        if (! array_key_exists($key, $config)) {
            return null;
        }

        $value = $config[$key];

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Gmail treats a `subject:foo bar` token as `subject:foo` AND `bar`.
     * To match a phrase with spaces, wrap it in double quotes.
     */
    private function quoteIfNeeded(string $value): string
    {
        if (preg_match('/\s/', $value) === 1) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }
}
