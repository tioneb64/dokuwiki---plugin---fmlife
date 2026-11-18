<?php

use dokuwiki\Extension\CLIPlugin;
use splitbrain\phpcli\Options;

/** php bin/plugin.php fmlife remind [--mail] */
class cli_plugin_fmlife extends CLIPlugin
{
    /** @inheritdoc */
    protected function setup(Options $options)
    {
        $options->setHelp('Liste les fiches expirées, qui expirent bientôt ou à revoir.');
        $options->registerCommand('remind', 'Affiche le rappel');
        $options->registerOption('mail', 'Envoie aussi le rappel à l\'adresse configurée', 'm', false, 'remind');
    }

    /** @inheritdoc */
    protected function main(Options $options)
    {
        /** @var helper_plugin_fmlife $helper */
        $helper = plugin_load('helper', 'fmlife');
        $text = $helper->reminderText();
        echo $text === '' ? "Rien à signaler.\n" : $text;
        if ($options->getOpt('mail')) {
            $this->info($helper->sendReminder() ? 'E-mail envoyé' : 'Aucun e-mail envoyé (adresse non configurée ou rien à signaler)');
        }
    }
}
