<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\Parsing\Handler;

/**
 * <fmreminders> : affiche le tableau de bord des fiches à traiter dans une page
 */
class syntax_plugin_fmlife extends SyntaxPlugin
{
    /** @inheritdoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritdoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritdoc */
    public function getSort()
    {
        return 250;
    }

    /** @inheritdoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('<fmreminders\s*/?>', $mode, 'plugin_fmlife');
        $this->Lexer->addSpecialPattern('<fmowners\s*/?>', $mode, 'plugin_fmlife');
    }

    /** @inheritdoc */
    public function handle($match, $state, $pos, Handler $handler)
    {
        return [str_contains($match, 'fmowners') ? 'owners' : 'reminders'];
    }

    /** @inheritdoc */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        if ($format !== 'xhtml') return false;
        if (!plugin_load('helper', 'fmeta')) {
            $renderer->doc .= '<div class="error">' . hsc(sprintf($this->getLang('need_fmeta'), 'fmlife')) . '</div>';
            return true;
        }
        // le contenu dépend de la date du jour et des droits de lecture
        $renderer->nocache();
        /** @var helper_plugin_fmlife $helper */
        $helper = $this->loadHelper('fmlife');
        $renderer->doc .= ($data[0] ?? '') === 'owners' ? $helper->ownersTable() : $helper->dashboard();
        return true;
    }
}
