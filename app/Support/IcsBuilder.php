<?php

namespace App\Support;

use Carbon\Carbon;

class IcsBuilder
{
    /**
     * Genera un ICS simple para un solo evento.
     *
     * $data keys esperadas:
     * uid, summary, description, location, start, end, organizer, attendees[]
     *
     * start/end pueden venir como "YYYY-MM-DD HH:MM"
     */
    public static function singleEvent(array $data): string
    {
        $tz = $data['tz'] ?? 'America/Bogota';

        $uid         = (string) ($data['uid'] ?? uniqid('event-') . '@sicefa.local');
        $summary     = self::escape((string) ($data['summary'] ?? 'Evento'));
        $description = self::escape((string) ($data['description'] ?? ''));
        $location    = self::escape((string) ($data['location'] ?? ''));
        $organizer   = (string) ($data['organizer'] ?? '');
        $attendees   = (array) ($data['attendees'] ?? []);

        // Convierte a formato ICS UTC: YYYYMMDDTHHMMSSZ
        $dtStart = self::toUtcIcsDateTime($data['start'] ?? null, $tz);
        $dtEnd   = self::toUtcIcsDateTime($data['end'] ?? null, $tz);

        $lines = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:-//SICEFA//SIGAC//ES';
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:REQUEST';
        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $uid;
        $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
        if ($dtStart) $lines[] = 'DTSTART:' . $dtStart;
        if ($dtEnd)   $lines[] = 'DTEND:' . $dtEnd;
        $lines[] = 'SUMMARY:' . $summary;
        if ($description !== '') $lines[] = 'DESCRIPTION:' . $description;
        if ($location !== '')    $lines[] = 'LOCATION:' . $location;

        if (filter_var($organizer, FILTER_VALIDATE_EMAIL)) {
            $lines[] = 'ORGANIZER:MAILTO:' . $organizer;
        }

        foreach ($attendees as $mail) {
            $mail = trim((string) $mail);
            if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
            $lines[] = 'ATTENDEE;CN=' . self::escape($mail) . ';RSVP=TRUE:MAILTO:' . $mail;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines)) . "\r\n";
    }

    private static function toUtcIcsDateTime($value, string $tz): ?string
    {
        if (!$value) return null;

        // Acepta "YYYY-MM-DD HH:MM" o Carbon/DateTime
        $dt = $value instanceof \DateTimeInterface
            ? Carbon::instance(\DateTime::createFromInterface($value))
            : Carbon::parse((string) $value, $tz);

        return $dt->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    private static function escape(string $text): string
    {
        // Escapes estándar ICS
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);
        $text = str_replace("\n", '\\n', $text);
        $text = str_replace(',', '\,', $text);
        $text = str_replace(';', '\;', $text);
        return $text;
    }

    private static function fold(string $line, int $limit = 73): string
    {
        // Plegado de líneas RFC5545 (muy básico)
        if (strlen($line) <= $limit) return $line;
        $out = '';
        while (strlen($line) > $limit) {
            $out .= substr($line, 0, $limit) . "\r\n" . ' ';
            $line = substr($line, $limit);
        }
        return $out . $line;
    }
}
