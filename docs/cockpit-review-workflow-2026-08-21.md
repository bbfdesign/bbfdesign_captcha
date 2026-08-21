# ADR: Spam-/Quarantäne-Review mit CaptchaCockpit

Datum: 2026-08-21  
Status: angenommen für Plugin 1.0.65, Cockpit-Gegenstück folgt

## Kontext

Björn braucht im CaptchaCockpit eine bedienbare Sicht auf geblockte oder geloggte
Formularversuche. Hashes und Fingerprints reichen für Flotten-Erkennung, sind für
Operator-Entscheidungen aber unbrauchbar. Gleichzeitig darf das Cockpit nicht zum
ungeprüften Zentralarchiv für personenbezogene Formulardaten werden.

## Aktueller Stand im Plugin

`CockpitTelemetryService` liest neue Zeilen aus `bbf_captcha_spam_log` und sendet
an `POST /api/v1/ingest` standardmäßig nur minimierte Signale:

- `ipHash`, optional `ipPrefix`
- `contentFp`, `contentShape`
- `emailDomain`, nie die volle Adresse
- `formType`, `action`, `score`, `detectionMethod`, `reasons`
- `userAgentHash`, `occurredAt`

Nur lokal im Shop bleiben:

- gespeicherte IP bzw. anonymisierte IP je nach `log_ip_anonymize`
- `request_data` aus dem Formular, vorher durch `PluginHelper::sanitizeRequestData`
  bereinigt
- User-Agent, Grundtext, False-Positive-/Zustellstatus

`SpamLog` speichert genug lokale Details für eine Shop-interne Review-/Nachsende-
Ansicht, aber ohne Cockpit-Vorschau müsste Björn pro Shop in das jeweilige Backend.

`CaptchaService` bleibt fail-open gegenüber Cockpit-Ausfällen: zentrale Rulesets
oder Blocklists dürfen den Shop-Schutz nicht brechen. Bei Spam entscheidet die
Formular-Konfiguration zwischen `blocked` und `logged`; blockierte Nachrichten
können lokal nachträglich zugestellt werden.

`CockpitFeedbackService` meldet Spam/Ham-Feedback bereits pseudonymisiert zurück
an `POST /api/v1/feedback` und kann so Ruleset/Training füttern.

## Entscheidung

Für das Plugin wird jetzt ein kleiner, rückwärtskompatibler Opt-in-Schnitt
umgesetzt:

- Standard-Ingest bleibt unverändert datensparsam.
- Neues Setting `cockpit_review_enabled`, Default AUS.
- Aktivierung nur mit bestehender Cockpit-AVV-/Datenschutz-Bestätigung.
- Nur bei `action=BLOCKED|LOGGED` wird optional ein redigierter `reviewSnippet`
  gesendet.
- Snippet-Limit: 180 Zeichen.
- Zentrale Redaction durch `CockpitReviewRedactor`.
- Dedizierte Namens-, E-Mail-, Telefon-, Adress-, Token- und Passwortfelder werden
  nicht in den Snippet-Korpus aufgenommen.
- Im verbleibenden Text werden typische E-Mails, URLs, Telefonnummern, Adressen,
  PLZ/Ort-Muster, Bestell-/Kundenreferenzen und lange Tokens maskiert.

Das ist bewusst keine Volltext-Quarantäne im Cockpit. Es ist eine Triage-Vorschau:
genug, um Muster wie "SEO-Linkspam", "Testsubmit", "Pharma-Welle" oder echte
Kundenanfrage grob zu erkennen, ohne Klar-IP, volle E-Mail oder vollständige
Formulardaten zentral zu speichern.

## Cockpit-API-Erweiterung

`POST /api/v1/ingest` akzeptiert künftig pro Event optional:

```json
{
  "reviewSnippet": "Kurze redigierte Vorschau...",
  "reviewSnippetVersion": "pii-redaction-v1",
  "reviewSnippetMaxChars": 180
}
```

Anforderungen ans Cockpit:

- unbekannte Felder rückwärtskompatibel ignorieren
- Snippets separat mit kurzer Retention speichern, empfohlen 14 Tage
- Snippet-Zugriff nur für Rollen `owner`, `security`, `support`
- Zugriffe und Feedback-Entscheidungen auditieren
- keine Volltextsuche über Snippets ohne zusätzliche Datenschutzfreigabe
- Anzeige klar als "redigierte Vorschau" kennzeichnen
- Feedback-Aktion `Spam`/`Kein Spam` an bestehenden Feedback-Fluss anbinden

Bestehende Signatur bleibt:

```text
X-Cockpit-Instance: <instanceId>
X-Signed-At: <unix timestamp>
X-Signature: hmac_sha256(rawBody + "|" + signedAt, cockpit_secret)
```

## Phase 2: Signierter Remote-Review-Endpoint

Für Detailprüfung ohne dauerhafte Klartextspeicherung im Cockpit wird empfohlen,
im nächsten Cockpit-/Plugin-Schnitt einen serverseitigen Abruf zu ergänzen:

- Cockpit erzeugt auf Klick ein kurzlebiges Review-Token mit Event-ID, Scope,
  Nonce und Ablaufzeit (max. 5 Minuten).
- Shop bietet einen Endpoint wie
  `POST /bbfdesign-captcha/api/v1/review-preview/{spamLogId}`.
- Request ist HMAC-signiert und an Instanz, Event, Ablaufzeit und Nonce gebunden.
- Shop liefert ausschließlich redigierte Vorschau, nie rohe IP, volle E-Mail,
  Passwörter, Tokens oder komplette Formularpayloads.
- Cockpit speichert die Antwort nicht dauerhaft, sondern zeigt sie transient an
  und schreibt nur Audit-/Feedback-Metadaten.

Damit muss Björn sich nicht in jeden Shop einloggen, das Cockpit wird aber trotzdem
nicht zum Klartext-Datenlager.

## Retention

Plugin:

- lokale Details folgen `log_retention_days` und `auto_cleanup`
- `log_request_data=0` verhindert lokale Detailpersistenz; dann kann auch kein
  Review-Snippet erzeugt werden

Cockpit:

- Standard-Telemetrie nach Cockpit-Retention
- Review-Snippets separat kurz halten, empfohlen 14 Tage
- Feedback-Metadaten dürfen länger bleiben, aber ohne Snippet-Inhalt

## Risiko und Grenzen

Pattern-Redaction ist keine juristisch perfekte Anonymisierung. Deshalb bleibt der
Modus aus, benötigt AVV-/Datenschutzbewusstsein und sendet nur kurze Vorschauen.
Für besonders sensible Mandanten ist Phase 2 mit transientem Remote-Abruf die
bessere Zielarchitektur.

## QA-Gates

- `bash tools/development-control.sh --local`
- PHP-Lint für geänderte PHP-Dateien
- `git diff --check`
