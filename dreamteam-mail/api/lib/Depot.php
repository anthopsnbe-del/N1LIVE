<?php
declare(strict_types=1);

/** Stockage des alias jetables (SQLite par defaut, MySQL si le DSN le dit). */
final class Depot
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->pdo = new PDO(
            $config['dsn'],
            $config['utilisateur'] ?? null,
            $config['mot_de_passe'] ?? null,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $this->creerTable();
    }

    private function creerTable(): void
    {
        $pilote = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $pilote === 'mysql'
            ? 'CREATE TABLE IF NOT EXISTS alias_jetables (
                   alias VARCHAR(120) NOT NULL PRIMARY KEY,
                   jeton_hash VARCHAR(64) NOT NULL,
                   cree_a BIGINT NOT NULL,
                   expire_a BIGINT NOT NULL,
                   empreinte_ip VARCHAR(64) NOT NULL,
                   INDEX idx_expire (expire_a),
                   INDEX idx_ip (empreinte_ip, cree_a)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            : 'CREATE TABLE IF NOT EXISTS alias_jetables (
                   alias TEXT PRIMARY KEY,
                   jeton_hash TEXT NOT NULL,
                   cree_a INTEGER NOT NULL,
                   expire_a INTEGER NOT NULL,
                   empreinte_ip TEXT NOT NULL
               )';
        $this->pdo->exec($sql);
        if ($pilote !== 'mysql') {
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_expire ON alias_jetables (expire_a)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ip ON alias_jetables (empreinte_ip, cree_a)');
        }
    }

    public function existe(string $alias): bool
    {
        $requete = $this->pdo->prepare('SELECT 1 FROM alias_jetables WHERE alias = ?');
        $requete->execute([$alias]);
        return (bool) $requete->fetchColumn();
    }

    public function creer(string $alias, string $jetonHash, int $creeA, int $expireA, string $ip): void
    {
        $this->pdo->prepare(
            'INSERT INTO alias_jetables (alias, jeton_hash, cree_a, expire_a, empreinte_ip)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$alias, $jetonHash, $creeA, $expireA, $ip]);
    }

    /** Alias valide et non expire, ou null. */
    public function lire(string $alias): ?array
    {
        $requete = $this->pdo->prepare('SELECT * FROM alias_jetables WHERE alias = ?');
        $requete->execute([$alias]);
        $ligne = $requete->fetch();
        return $ligne === false ? null : $ligne;
    }

    public function supprimer(string $alias): void
    {
        $this->pdo->prepare('DELETE FROM alias_jetables WHERE alias = ?')->execute([$alias]);
    }

    /** @return string[] alias arrives a expiration. */
    public function expires(int $maintenant, int $limite = 200): array
    {
        $requete = $this->pdo->prepare(
            'SELECT alias FROM alias_jetables WHERE expire_a > 0 AND expire_a <= ?
             ORDER BY expire_a LIMIT ' . (int) $limite
        );
        $requete->execute([$maintenant]);
        return array_column($requete->fetchAll(), 'alias');
    }

    public function creationsRecentes(string $ip, int $depuis): int
    {
        $requete = $this->pdo->prepare(
            'SELECT COUNT(*) FROM alias_jetables WHERE empreinte_ip = ? AND cree_a >= ?'
        );
        $requete->execute([$ip, $depuis]);
        return (int) $requete->fetchColumn();
    }

    public function nombreActifs(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM alias_jetables')->fetchColumn();
    }
}
