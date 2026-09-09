<?php
declare(strict_types=1);

/** Lecture d'un message brut : en-tetes decodes et premiere partie texte. */
final class Mime
{
    public static function analyser(string $brut): array
    {
        [$entetes, $corps] = self::separer($brut);
        $type = $entetes['content-type'] ?? 'text/plain';
        $encodage = strtolower(trim($entetes['content-transfer-encoding'] ?? '7bit'));

        return [
            'expediteur' => self::decoderEntete($entetes['from'] ?? ''),
            'sujet'      => self::decoderEntete($entetes['subject'] ?? ''),
            'date'       => self::decoderEntete($entetes['date'] ?? ''),
            'message_id' => trim($entetes['message-id'] ?? ''),
            'corps'      => self::extraireTexte($corps, $type, $encodage),
        ];
    }

    /** @return array{0: array<string,string>, 1: string} */
    private static function separer(string $brut): array
    {
        $brut = str_replace("\r\n", "\n", $brut);
        $coupure = strpos($brut, "\n\n");
        if ($coupure === false) {
            return [[], $brut];
        }
        $bloc = substr($brut, 0, $coupure);
        $corps = substr($brut, $coupure + 2);

        // Les en-tetes replies sur plusieurs lignes commencent par une espace.
        $bloc = preg_replace('/\n[ \t]+/', ' ', $bloc) ?? $bloc;
        $entetes = [];
        foreach (explode("\n", $bloc) as $ligne) {
            $point = strpos($ligne, ':');
            if ($point !== false) {
                $entetes[strtolower(trim(substr($ligne, 0, $point)))] = trim(substr($ligne, $point + 1));
            }
        }
        return [$entetes, $corps];
    }

    private static function extraireTexte(string $corps, string $type, string $encodage, int $profondeur = 0): string
    {
        if ($profondeur > 6) {
            return '';
        }
        if (stripos($type, 'multipart/') === 0 && preg_match('/boundary="?([^";]+)"?/i', $type, $trouve)) {
            $separateur = '--' . trim($trouve[1]);
            $parties = explode($separateur, $corps);
            $repli = '';
            foreach (array_slice($parties, 1) as $partie) {
                $partie = ltrim($partie, "\n");
                if ($partie === '' || str_starts_with($partie, '--')) {
                    continue;
                }
                [$sousEntetes, $sousCorps] = self::separer($partie);
                $sousType = $sousEntetes['content-type'] ?? 'text/plain';
                $sousEncodage = strtolower(trim($sousEntetes['content-transfer-encoding'] ?? '7bit'));
                $texte = self::extraireTexte($sousCorps, $sousType, $sousEncodage, $profondeur + 1);
                if ($texte === '') {
                    continue;
                }
                if (stripos($sousType, 'text/plain') === 0) {
                    return $texte;  // le texte brut est toujours prefere
                }
                $repli = $repli !== '' ? $repli : $texte;
            }
            return $repli;
        }

        $texte = match ($encodage) {
            'base64' => (string) base64_decode($corps, true),
            'quoted-printable' => quoted_printable_decode($corps),
            default => $corps,
        };
        if (stripos($type, 'text/html') === 0) {
            $texte = self::htmlEnTexte($texte);
        } elseif (stripos($type, 'text/') !== 0 && $type !== '') {
            return '';  // pieces jointes et binaires : ignorees
        }
        return self::versUtf8($texte, $type);
    }

    private static function htmlEnTexte(string $html): string
    {
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>|</p>|</div>|</tr>#i', "\n", $html) ?? $html;
        $texte = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace("/\n{3,}/", "\n\n", $texte) ?? $texte);
    }

    private static function versUtf8(string $texte, string $type): string
    {
        if (preg_match('/charset="?([^";]+)"?/i', $type, $trouve)) {
            $jeu = strtoupper(trim($trouve[1]));
            if ($jeu !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $converti = @mb_convert_encoding($texte, 'UTF-8', $jeu);
                if (is_string($converti)) {
                    $texte = $converti;
                }
            }
        }
        // Toute sequence invalide restante casserait json_encode.
        return mb_check_encoding($texte, 'UTF-8')
            ? $texte
            : (string) mb_convert_encoding($texte, 'UTF-8', 'ISO-8859-1');
    }

    public static function decoderEntete(string $valeur): string
    {
        if ($valeur === '') {
            return '';
        }
        if (function_exists('iconv_mime_decode')) {
            $decode = @iconv_mime_decode($valeur, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decode) && $decode !== '') {
                return $decode;
            }
        }
        if (function_exists('mb_decode_mimeheader')) {
            return mb_decode_mimeheader($valeur);
        }
        return $valeur;
    }
}
