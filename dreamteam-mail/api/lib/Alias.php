<?php
declare(strict_types=1);

/** Generation et validation des parties locales jetables. */
final class Alias
{
    private const ADJECTIFS = ['vif','calme','clair','doux','franc','leger','malin','net',
        'rapide','sage','vaste','zele','brave','fier','juste'];
    private const NOMS = ['aigle','banc','cedre','delta','ecume','faucon','givre','havre',
        'ilot','jade','kayak','lynx','menthe','nuage','orage','pivoine'];

    /** Boites reelles et adresses techniques : jamais distribuees. */
    public const RESERVEES = ['abuse','admin','administrateur','administrator','billing','bounce',
        'catchall','clips','contact','facturation','hostmaster','info','mail','mail_php',
        'mailer-daemon','marketing','no-reply','noreply','postmaster','root','sales','security',
        'support','webmaster'];

    public static function generer(): string
    {
        return sprintf(
            '%s.%s%03d',
            self::ADJECTIFS[random_int(0, count(self::ADJECTIFS) - 1)],
            self::NOMS[random_int(0, count(self::NOMS) - 1)],
            random_int(0, 999)
        );
    }

    public static function valide(string $alias): bool
    {
        return $alias !== ''
            && strlen($alias) <= 32
            && preg_match('/^[a-z0-9](?:[a-z0-9._-]{0,30}[a-z0-9])?$/', $alias) === 1
            && !in_array($alias, self::RESERVEES, true);
    }
}
