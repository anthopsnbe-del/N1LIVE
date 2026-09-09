<?php
declare(strict_types=1);

/**
 * Client IMAP minimal, en sockets purs.
 *
 * L'extension php-imap n'est pas garantie sur un hebergement mutualise : cette
 * classe n'implemente que ce dont l'API a besoin (SEARCH, FETCH, STORE,
 * EXPUNGE) en commandes UID, pour ne pas dependre des numeros de sequence.
 */
final class Imap
{
    /** @var resource */
    private $flux;
    private int $compteur = 0;

    public function __construct(
        private string $hote,
        private int $port,
        private string $utilisateur,
        private string $motDePasse,
        private bool $ssl = true,
        private int $delai = 20,
    ) {}

    public function connecter(): void
    {
        $adresse = ($this->ssl ? 'ssl://' : 'tcp://') . $this->hote . ':' . $this->port;
        $contexte = stream_context_create(['ssl' => [
            'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true,
        ]]);
        $flux = @stream_socket_client(
            $adresse, $code, $erreur, $this->delai, STREAM_CLIENT_CONNECT, $contexte
        );
        if ($flux === false) {
            throw new RuntimeException("Connexion IMAP impossible ({$erreur})");
        }
        $this->flux = $flux;
        stream_set_timeout($this->flux, $this->delai);
        $this->lireLigne(); // salutation du serveur
        if (!$this->ssl) {
            $this->commande('STARTTLS');
            if (!stream_socket_enable_crypto($this->flux, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Passage en TLS impossible');
            }
        }
        $this->commande('LOGIN ' . $this->citer($this->utilisateur) . ' ' . $this->citer($this->motDePasse));
    }

    public function selectionner(string $dossier, bool $lectureSeule = true): void
    {
        $this->commande(($lectureSeule ? 'EXAMINE ' : 'SELECT ') . $this->citer($dossier));
    }

    /** @return string[] UID des messages adresses a cet alias. */
    public function chercherPourDestinataire(string $adresse): array
    {
        $lignes = $this->commande('UID SEARCH (TO ' . $this->citer($adresse) . ')');
        foreach ($lignes as $ligne) {
            if (preg_match('/^\* SEARCH(.*)$/i', trim($ligne), $trouve)) {
                return preg_split('/\s+/', trim($trouve[1]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        }
        return [];
    }

    /** Message brut (RFC822) pour un UID donne, ou null s'il a disparu. */
    public function messageBrut(string $uid): ?string
    {
        $lignes = $this->commande('UID FETCH ' . $uid . ' (BODY.PEEK[])');
        foreach ($lignes as $ligne) {
            if (str_starts_with($ligne, "\x00LITTERAL")) {
                return substr($ligne, strlen("\x00LITTERAL"));
            }
        }
        return null;
    }

    /** Supprime un unique message designe par son UID. */
    public function supprimerUn(string $uid): bool
    {
        if (!preg_match('/^\d+$/', $uid)) {
            return false;
        }
        return $this->supprimer([$uid]) === 1;
    }

    /** Marque puis efface definitivement les messages designes. */
    public function supprimer(array $uids): int
    {
        if ($uids === []) {
            return 0;
        }
        $this->commande('UID STORE ' . implode(',', $uids) . ' +FLAGS (\\Deleted)');
        $this->commande('EXPUNGE');
        return count($uids);
    }

    public function fermer(): void
    {
        if (isset($this->flux) && is_resource($this->flux)) {
            @$this->commande('LOGOUT');
            @fclose($this->flux);
        }
    }

    /** Envoie une commande et retourne les lignes de reponse (litteraux inclus). */
    private function commande(string $commande): array
    {
        $tag = sprintf('A%03d', ++$this->compteur);
        fwrite($this->flux, $tag . ' ' . $commande . "\r\n");

        $lignes = [];
        while (true) {
            $ligne = $this->lireLigne();
            if ($ligne === null) {
                throw new RuntimeException('Connexion IMAP interrompue');
            }
            // Reponse avec litteral : {n} annonce n octets a lire tels quels.
            if (preg_match('/\{(\d+)\}\r?\n?$/', $ligne, $trouve)) {
                $lignes[] = rtrim($ligne, "\r\n");
                $lignes[] = "\x00LITTERAL" . $this->lireOctets((int) $trouve[1]);
                continue;
            }
            if (str_starts_with($ligne, $tag . ' ')) {
                $etat = strtoupper(substr($ligne, strlen($tag) + 1, 2));
                if ($etat !== 'OK') {
                    throw new RuntimeException('IMAP a refuse : ' . trim($ligne));
                }
                return $lignes;
            }
            $lignes[] = rtrim($ligne, "\r\n");
        }
    }

    private function lireLigne(): ?string
    {
        $ligne = fgets($this->flux, 8192);
        return $ligne === false ? null : $ligne;
    }

    private function lireOctets(int $taille): string
    {
        $donnees = '';
        while (strlen($donnees) < $taille) {
            $morceau = fread($this->flux, $taille - strlen($donnees));
            if ($morceau === false || $morceau === '') {
                break;
            }
            $donnees .= $morceau;
        }
        return $donnees;
    }

    private function citer(string $valeur): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $valeur) . '"';
    }
}
