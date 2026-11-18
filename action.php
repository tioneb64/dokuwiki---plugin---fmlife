<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

/**
 * Fmlife : page ?do=fmlife (tableau de bord) et rappel quotidien par e-mail
 */
class action_plugin_fmlife extends ActionPlugin
{
    /** @inheritdoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('ACTION_ACT_PREPROCESS', 'BEFORE', $this, 'actPreprocess');
        $controller->register_hook('TPL_ACT_UNKNOWN', 'BEFORE', $this, 'tplUnknown');
        $controller->register_hook('INDEXER_TASKS_RUN', 'AFTER', $this, 'dailyMail');
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
