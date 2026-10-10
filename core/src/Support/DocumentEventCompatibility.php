<?php
namespace EvolutionCMS\Support;

/** Preserve existing plugin subscriptions while adopting DocumentManager PR #1. */
final class DocumentEventCompatibility
{
    public const ALIASES = [
        'OnBeforeDocSave' => 'OnBeforeDocFormSave',
        'OnDocSave' => 'OnDocFormSave',
        'OnBeforeDocDelete' => 'OnBeforeDocFormDelete',
        'OnDocDelete' => 'OnDocFormDelete',
        'OnDocUndelete' => 'OnDocFormUnDelete',
        'OnDocPublish' => 'OnDocPublished',
        'OnDocUnpublish' => 'OnDocUnPublished',
    ];

    public static function parameters(string $event, array $params): array
    {
        if ($event === 'OnBeforeDocSave' || $event === 'OnDocSave') {
            $params['mode'] = ($params['action'] ?? '') === 'create' ? 'new' : 'upd';
            if (array_key_exists('documentData', $params)) {
                $params['doc'] =& $params['documentData'];
            } elseif (isset($params['document'])) {
                $params['doc'] = $params['document']->toArray();
            }
        }
        if ($event === 'OnDocPublish' || $event === 'OnDocUnpublish') {
            $params['docid'] = $params['id'];
        }
        return $params;
    }
}
