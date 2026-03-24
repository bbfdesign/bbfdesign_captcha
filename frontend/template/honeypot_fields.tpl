{* BBF Honeypot Fields – Werden per SmartyOutputFilter automatisch injiziert *}
{* WICHTIG: Diese Datei wird NICHT direkt eingebunden! *}
{* Die Honeypot-Felder werden serverseitig von HoneypotService::renderFields() generiert *}
{* und per SmartyOutputFilter::filter() in den HTML-Output injiziert. *}
{*
   Honeypot-Regeln:
   - KEIN display:none (Bots erkennen das!)
   - Off-Screen-Positionierung: position:absolute; left:-9999px
   - aria-hidden="true" für Screen-Reader
   - tabindex="-1" zum Überspringen in Tab-Reihenfolge
   - autocomplete="off" gegen Autofill
   - Dynamische Feldnamen pro Session (Salt-basiert)
*}
