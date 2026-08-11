<?php

declare(strict_types=1);

namespace Plugin\bbfdesign_captcha\src\Services;

use JTL\DB\DbInterface;
use JTL\Shop;
use Plugin\bbfdesign_captcha\src\Models\Setting;

/**
 * CAP-17 – blockierte Nachricht nachträglich zustellen.
 *
 * Björn: „Auch wenn man etwas zustimmt, muss man hier ja in der Lage sein, die
 * Nachricht doch noch zu erhalten." Genau daran fehlte es: Ein Fehlalarm kostete
 * bislang die Anfrage eines echten Kunden – abgewiesen, im Log sichtbar, aber
 * unwiederbringlich für den Empfänger.
 *
 * Der Dienst baut aus den protokollierten Formulardaten (`request_data`) eine
 * lesbare Mail und schickt sie über das Shop-Mailsystem an die konfigurierte
 * Adresse. Ein Vermerk `delivered_at` verhindert versehentliches Doppelsenden.
 *
 * Voraussetzung: „Formulardaten protokollieren" war zum Zeitpunkt der Blockade
 * aktiv – ohne die Daten gibt es nichts zuzustellen, und das sagt der Dienst
 * dann auch klar.
 */
class BlockedMessageDelivery
{
    private DbInterface $db;
    private Setting $settings;

    public function __construct(DbInterface $db, Setting $settings)
    {
        $this->db       = $db;
        $this->settings = $settings;
    }

    /** Empfängeradresse: Einstellung → Master-Absender des Shops. */
    public function recipient(): string
    {
        $configured = trim($this->settings->get('delivery_recipient'));
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            return $configured;
        }

        try {
            $master = trim((string)(Shop::getSettingValue(\CONF_EMAILS, 'email_master_absender') ?? ''));
            if ($master !== '' && filter_var($master, FILTER_VALIDATE_EMAIL)) {
                return $master;
            }
        } catch (\Throwable) {
        }

        return '';
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function deliver(int $logId, string $overrideRecipient = ''): array
    {
        $entry = $this->db->getSingleObject(
            'SELECT * FROM bbf_captcha_spam_log WHERE id = :id',
            ['id' => $logId]
        );
        if ($entry === null) {
            return ['success' => false, 'message' => 'Eintrag nicht gefunden.'];
        }
        if (!empty($entry->delivered_at)) {
            return [
                'success' => false,
                'message' => 'Diese Nachricht wurde bereits am ' . $entry->delivered_at . ' zugestellt.',
            ];
        }

        $fields = $this->decodeFields((string)($entry->request_data ?? ''));
        if ($fields === []) {
            return [
                'success' => false,
                'message' => 'Zu diesem Eintrag wurden keine Formulardaten protokolliert – es gibt nichts zuzustellen. '
                    . '(Einstellungen → „Formulardaten protokollieren“ aktivieren, damit das künftig geht.)',
            ];
        }

        $to = trim($overrideRecipient) !== '' ? trim($overrideRecipient) : $this->recipient();
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Keine gültige Empfängeradresse. Bitte unter Einstellungen eine Zustell-Adresse hinterlegen.',
            ];
        }

        $formType = (string)($entry->form_type ?? 'unbekannt');
        $subject  = 'Nachgereicht: blockierte Formular-Einreichung (' . $formType . ')';
        $html     = $this->buildHtml($entry, $fields);

        $logService = new SpamLogService($this->db, $this->settings);
        if (!$logService->sendMail($to, $subject, $html)) {
            return ['success' => false, 'message' => 'Versand fehlgeschlagen – siehe Shop-Log.'];
        }

        // Der Vermerk ist Komfort, nicht Bedingung: Bei reinem Datei-Deploy läuft
        // die Migration nicht zwangsläufig mit, dann fehlt die Spalte. Die Mail
        // ist dann trotzdem raus – das darf nicht als Fehler enden.
        $noted = true;
        try {
            $this->db->queryPrepared(
                'UPDATE bbf_captcha_spam_log SET delivered_at = NOW() WHERE id = :id',
                ['id' => $logId]
            );
        } catch (\Throwable) {
            $noted = false;
        }

        return [
            'success' => true,
            'message' => 'Nachricht an ' . $to . ' zugestellt.'
                . ($noted ? '' : ' (Hinweis: konnte nicht als zugestellt vermerkt werden – Plugin-Update ausführen.)'),
        ];
    }

    /** @return array<string,string> */
    private function decodeFields(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        $this->flatten($decoded, $out);

        // Technische Felder tragen für den Empfänger nichts bei.
        $skip = ['jtl_token', 'jtl_hp_input', 'bbf_ct', 'bbf_altcha', 'bbf_js_token', 'form', 'kKundengruppe'];
        foreach ($skip as $k) {
            unset($out[$k]);
        }
        foreach (array_keys($out) as $k) {
            if (str_starts_with((string)$k, 'bbf-captcha-hp') || str_starts_with((string)$k, 'consent_')) {
                unset($out[$k]);
            }
        }

        return $out;
    }

    /**
     * @param array<mixed> $in
     * @param array<string,string> $out
     */
    private function flatten(array $in, array &$out, string $prefix = ''): void
    {
        foreach ($in as $key => $value) {
            $name = $prefix === '' ? (string)$key : $prefix . '.' . $key;
            if (is_array($value)) {
                $this->flatten($value, $out, $name);
                continue;
            }
            if (is_scalar($value)) {
                $out[$name] = (string)$value;
            }
        }
    }

    /** @param array<string,string> $fields */
    private function buildHtml(object $entry, array $fields): string
    {
        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $rows = '';
        foreach ($fields as $label => $value) {
            $rows .= '<tr>'
                . '<td style="padding:6px 12px 6px 0;vertical-align:top;color:#8b95b5;white-space:nowrap;">'
                . $esc((string)$label) . '</td>'
                . '<td style="padding:6px 0;vertical-align:top;color:#1b2559;">'
                . nl2br($esc($value)) . '</td>'
                . '</tr>';
        }

        $method = trim((string)($entry->detection_method ?? ''));
        $when   = (string)($entry->created_at ?? '');
        $form   = (string)($entry->form_type ?? '');
        $score  = (string)($entry->spam_score ?? '');

        return '<div style="font-family:Manrope,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.5;color:#364468;">'
            . '<p style="margin:0 0 12px;">Diese Formular-Einreichung wurde vom Spam-Schutz abgewiesen und '
            . 'im Nachhinein als berechtigt eingestuft. Hier ist ihr Inhalt.</p>'
            . '<table style="border-collapse:collapse;margin:0 0 16px;">' . $rows . '</table>'
            . '<p style="margin:0;padding-top:12px;border-top:1px solid #e3e9f7;color:#8b95b5;font-size:12px;">'
            . 'Eingegangen am ' . $esc($when) . ' &middot; Formular ' . $esc($form)
            . ' &middot; Score ' . $esc($score)
            . ($method !== '' ? ' &middot; erkannt durch ' . $esc($method) : '')
            . '</p></div>';
    }
}
