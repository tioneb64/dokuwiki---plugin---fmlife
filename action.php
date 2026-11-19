<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

/**
 * Fmlife : page ?do=fmlife (tableau de bord) et rappel quotidien par e-mail
 */
class action_plugin_fmlife extends ActionPlugin
{
    /** Le plugin fmeta, dont ce plugin dépend, manque : message pour les gestionnaires du wiki */
    public function missingBase(Event $event)
    {
        if (auth_ismanager()) msg(sprintf($this->getLang('need_fmeta'), 'fmlife'), -1);
    }

    /** @inheritdoc */
    public function register(EventHandler $controller)
    {
        if (!plugin_load('helper', 'fmeta')) {
            // le plugin de base manque : on prévient les gestionnaires et on ne branche rien d'autre
            $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'missingBase');
            return;
        }
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'actPreprocess');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'tplUnknown');
        $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'dailyMail');
        // points d'extension de fmeta
        $controller->register_hook('FMETA_ENTRY_ENRICH', 'BEFORE', $this, 'enrich');
        $controller->register_hook('FMETA_BADGE', 'BEFORE', $this, 'badge');
        $controller->register_hook('FMETA_ALERTS', 'BEFORE', $this, 'alerts');
    }

    /** FMETA_ENTRY_ENRICH : statut effectif et champs calculés (expire, reference, echeance, retard) */
    public function enrich(Event $event)
    {
        /** @var helper_plugin_fmlife $h */
        $h = plugin_load('helper', 'fmlife');
        $e = $event->data['entry'];
        $e['eff'] = $h->effective($e);
        $e['computed'] = [
            'expire' => ($e['expire'] ?? '') === '' ? $h->expiry($e) : '',
            'reference' => $h->reference($e) ?: null,
            'echeance' => $h->nextReview($e) ?: null,
            'retard' => $h->overdueDays($e),
        ];
        $event->data['entry'] = $e;
    }

    /** FMETA_BADGE : pastille de statut */
    public function badge(Event $event)
    {
        /** @var helper_plugin_fmlife $h */
        $h = plugin_load('helper', 'fmlife');
        $event->data['html'] = $h->badge((string)$event->data['raw'], $event->data['entry']['status'] ?? null);
    }

    /** FMETA_ALERTS : alertes d'expiration et de revue */
    public function alerts(Event $event)
    {
        $event->data['supported'] = true;
        if (!$event->data['entry']) return;
        /** @var helper_plugin_fmlife $h */
        $h = plugin_load('helper', 'fmlife');
        $event->data['alerts'] = array_merge($event->data['alerts'], $h->alerts($event->data['entry']));
    }

    public function actPreprocess(Event $event)
    {
        if ($event->data === 'fmlife') {
            $event->preventDefault();
            $event->stopPropagation();
        }
    }

    public function tplUnknown(Event $event)
    {
        if ($event->data !== 'fmlife') return;
        $event->preventDefault();

        /** @var helper_plugin_fmlife $helper */
        $helper = plugin_load('helper', 'fmlife');
        echo '<h1>' . hsc($this->getLang('dash_title')) . '</h1>';
        echo $helper->dashboard();
    }

    /** Au plus un e-mail par jour, déclenché par l'activité du wiki, si une adresse est configurée */
    public function dailyMail(Event $event)
    {
        global $conf;
        if (trim((string)$this->getConf('mailto')) === '') return;

        $stamp = $conf['metadir'] . '/fmlife.lastmail';
        if (@file_get_contents($stamp) === date('Y-m-d')) return;
        io_saveFile($stamp, date('Y-m-d'));

        /** @var helper_plugin_fmlife $helper */
        $helper = plugin_load('helper', 'fmlife');
        $helper->sendReminder();
    }
}
