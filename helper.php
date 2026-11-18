<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\Utf8\Clean;

/**
 * Fmlife : statuts, expiration et alertes des fiches
 */
class helper_plugin_fmlife extends Plugin
{
    /** Statuts connus : clé => [icône, couleur] ; le libellé vient de la langue (st_<clé>) */
    public const STATUSES = [
        'brouillon' => ['🌱', '#6b7280'],
        'a-valider' => ['🔎', '#c77700'],
        'validee'   => ['✅', '#2e7d32'],
        'expiree'   => ['⌛', '#b3261e'],
        'obsolete'  => ['🗄️', '#555555'],
        'archivee'  => ['📦', '#8a98a6'],
    ];

    /** Statuts pour lesquels on ne rappelle plus rien */
    protected const CLOSED = ['obsolete', 'archivee'];

    /** Ramène un statut saisi librement (« 🌱 brouillon », « à-valider-dsi ») à une clé connue */
    public function key(string $raw): string
    {
        $s = mb_strtolower(Clean::deaccent($raw));
        $s = trim(preg_replace('/[^a-z0-9]+/', ' ', $s));
        if ($s === '') return '';
        // vocabulaire du coffre Obsidian et des tâches : assimilé aux statuts du wiki
        if (str_contains($s, 'consolidation')) return 'a-valider';
        if (preg_match('/brouillon|draft|premier jet|completer|en cours|in progress|^open$|ouvert/', $s)) return 'brouillon';
        if (preg_match('/valider|review|relecture/', $s)) return 'a-valider';
        if (preg_match('/valid|approuv|consolid/', $s)) return 'validee';
        if (str_contains($s, 'expir')) return 'expiree';
        if (str_contains($s, 'obsol')) return 'obsolete';
        if (preg_match('/archiv|^done$|termine|^fait$|clos/', $s)) return 'archivee';
        return $s;
    }

    /** Vrai si la fiche est dans un espace de noms exclu des rappels (réglage exclude_ns, ex. les tâches) */
    public function excluded(array $e): bool
    {
        global $ID;
        $id = (string)($e['id'] ?? $ID ?? '');
        foreach (preg_split('/[\s,;]+/', (string)$this->getConf('exclude_ns'), -1, PREG_SPLIT_NO_EMPTY) as $ns) {
            $ns = trim($ns, ':');
            if ($id === $ns || str_starts_with($id, $ns . ':')) return true;
        }
        return false;
    }

    /** Statut effectif : une fiche validée dont la date d'expiration est passée est « expirée » */
    public function effective(array $e): string
    {
        $key = $this->key((string)($e['status'] ?? ''));
        if ($key === 'validee') {
            $exp = $this->expiry($e);
            if ($exp !== '' && $exp < date('Y-m-d')) return 'expiree';
        }
        // un statut hors vocabulaire garde son texte d'origine
        return isset(self::STATUSES[$key]) ? $key : $this->plain((string)($e['status'] ?? ''));
    }

    /** Date d'expiration : celle de l'en-tête, sinon dernière revue + durée de validité configurée */
    public function expiry(array $e): string
    {
        if (!empty($e['expire'])) return $e['expire'];
        $months = (int)$this->getConf('validity_months');
        $ref = $this->reference($e);
        if ($months <= 0 || $ref === '') return '';
        return (new DateTime($ref))->modify("+$months months")->format('Y-m-d');
    }

    /** Date de référence de la dernière revue : revue, sinon création, sinon dernière modification */
    public function reference(array $e): string
    {
        if (!empty($e['revue'])) return $e['revue'];
        if (!empty($e['created'])) return $e['created'];
        return !empty($e['mtime']) ? date('Y-m-d', (int)$e['mtime']) : '';
    }

    public function label(string $key): string
    {
        return $this->getLang('st_' . $key) ?: ucfirst(str_replace('-', ' ', $key));
    }

    /** Texte d'un statut hors vocabulaire, sans emoji ni ponctuation de tête, en minuscules */
    public function plain(string $raw): string
    {
        return mb_strtolower(trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($raw))));
    }

    /**
     * Pastille de statut
     *
     * @param string $raw statut (clé connue ou texte libre)
     * @param string|null $display texte d'origine, dont l'emoji de tête sert d'icône pour un statut libre
     */
    public function badge(string $raw, ?string $display = null): string
    {
        $key = $this->key($raw);
        if ($key === '') return '';
        if (isset(self::STATUSES[$key])) {
            [$icon, $color] = self::STATUSES[$key];
            $value = $key;
            $text = $this->label($key);
        } else {
            $src = trim($display ?? $raw);
            $text = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $src));
            $icon = trim(mb_substr($src, 0, mb_strlen($src) - mb_strlen($text))) ?: '🏷️';
            $color = '#6b7280';
            $value = $this->plain($src);
            $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
        }
        $title = trim($display ?? $raw);
        return '<a class="fm-badge" title="' . hsc($title) . '" style="background:' . $color . ';color:#fff" href="' .
            $this->fmeta()->url('status', $value) . '">' . $icon . ' ' . hsc($text) . '</a>';
    }

    /** Alertes d'une fiche : expirée, expire bientôt, à revoir */
    public function alerts(array $e): array
    {
        // une page sans en-tête (seulement des #hashtags) n'a pas de cycle de vie
        if (($e['hdr'] ?? true) === false || $this->excluded($e)) return [];
        $key = $this->key((string)($e['status'] ?? ''));
        if (in_array($key, self::CLOSED, true)) return [];

        $out = [];
        $today = new DateTime('today');
        $exp = $this->expiry($e);

        if ($exp !== '') {
            $days = (int)$today->diff(new DateTime($exp))->format('%r%a');
            if ($days < 0) {
                $out[] = ['level' => 'danger', 'text' => sprintf($this->getLang('al_expired'), $this->fmeta()->dateLabel($exp), -$days)];
            } elseif ($days <= (int)$this->getConf('warn_days')) {
                $out[] = ['level' => 'warning', 'text' => sprintf($this->getLang('al_expiring'), $this->fmeta()->dateLabel($exp), $days)];
            }
        }

        $ref = $this->reference($e);
        $limit = (int)$this->getConf('review_months');
        if ($ref !== '' && $limit > 0) {
            $age = new DateTime($ref);
            $months = $age > $today ? 0 : $age->diff($today)->y * 12 + $age->diff($today)->m;
            if ($months >= $limit) {
                $out[] = ['level' => 'warning', 'text' => sprintf($this->getLang('al_review'), $months, $limit)];
            }
        }
        return $out;
    }

    /**
     * Fiches nécessitant une action
     *
     * @return array{expired:array,expiring:array,review:array}
     */
    public function due(): array
    {
        $res = ['expired' => [], 'expiring' => [], 'review' => []];
        foreach ($this->fmeta()->query() as $e) {
            if (!$e['hdr'] || $this->excluded($e)) continue;
            $key = $this->key((string)$e['status']);
            if (in_array($key, self::CLOSED, true)) continue;
            $exp = $this->expiry($e);
            $days = $exp === '' ? null : (int)(new DateTime('today'))->diff(new DateTime($exp))->format('%r%a');
            if ($days !== null && $days < 0) {
                $res['expired'][] = $e;
            } elseif ($days !== null && $days <= (int)$this->getConf('warn_days')) {
                $res['expiring'][] = $e;
            } else {
                foreach ($this->alerts($e) as $a) {
                    if ($a['level'] === 'warning') {
                        $res['review'][] = $e;
                        break;
                    }
                }
            }
        }
        return $res;
    }

    /** Tableau de bord HTML : fiches par statut et fiches à traiter */
    public function dashboard(): string
    {
        $fm = $this->fmeta();
        $due = $this->due();
        $h = '<div class="plugin_fmlife">';

        $counts = [];
        $drafts = [];
        foreach ($fm->query() as $e) {
            if (!$e['hdr'] || $this->excluded($e)) continue;
            $k = $e['eff'] ?: '-';
            $counts[$k] = ($counts[$k] ?? 0) + 1;
            if ($this->key((string)$e['status']) === 'brouillon') $drafts[] = $e;
        }
        $h .= '<p class="fl-counts">';
        foreach ($counts as $k => $n) {
            $h .= ($k === '-' ? hsc($this->getLang('st_none')) : $this->badge($k)) . ' <strong>' . $n . '</strong> &nbsp; ';
        }
        $h .= '</p>';

        foreach (['expired' => 'sec_expired', 'expiring' => 'sec_expiring', 'review' => 'sec_review'] as $k => $lang) {
            $h .= '<h2>' . hsc(sprintf($this->getLang($lang), count($due[$k]))) . '</h2>' . $fm->table($due[$k], true);
        }
        $h .= '<h2>' . hsc(sprintf($this->getLang('sec_draft'), count($drafts))) . '</h2>' . $fm->table($drafts);
        return $h . '</div>';
    }

    /** Texte brut du rappel (e-mail, CLI) */
    public function reminderText(): string
    {
        global $conf;
        $due = $this->due();
        $lines = [];
        foreach (['expired' => 'sec_expired', 'expiring' => 'sec_expiring', 'review' => 'sec_review'] as $k => $lang) {
            if (!$due[$k]) continue;
            $lines[] = sprintf($this->getLang($lang), count($due[$k]));
            foreach ($due[$k] as $e) {
                $lines[] = '  - ' . $e['title'] . ' : ' . DOKU_URL . ltrim(wl($e['id'], '', false, '&'), '/') . ' (' .
                    implode(' ; ', array_column($this->alerts($e), 'text')) . ')';
            }
            $lines[] = '';
        }
        return $lines ? implode("\n", $lines) : '';
    }

    /** Envoie le rappel par e-mail à l'adresse configurée ; false s'il n'y a rien à envoyer */
    public function sendReminder(): bool
    {
        $to = trim((string)$this->getConf('mailto'));
        $text = $this->reminderText();
        if ($to === '' || $text === '') return false;

        $mail = new Mailer();
        $mail->to($to);
        $mail->subject($this->getLang('mail_subject'));
        $mail->setBody($text);
        return (bool)$mail->send();
    }

    /** @return helper_plugin_fmeta */
    protected function fmeta()
    {
        return plugin_load('helper', 'fmeta');
    }
}
