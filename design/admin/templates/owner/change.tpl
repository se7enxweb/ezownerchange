{* owner/change: the preview (dry run) of an owner change, its confirmation and its result.

   Drawn with the shared administration components only (.a4-*, components.css), no CSS of its own; admin, admin3,
   admin4, admin4l, the Admin UI and the editor find it through their design fallback to design/admin.
   Every button posts the same form back to owner/change; ezformtoken adds its token to it.
   Variables: object, node, new_owner, current_owner, scope, only_current_owner, can_subtree, subtree_count,
   web_limit, errors, notices, counts, items, list_limit, fingerprint, done, self_uri, back_uri, publish_new_version.
   Guide: extension/ezownerchange/doc/usage.md *}

{def $ctx = 'extension/ezownerchange'}

<form method="post" action={$self_uri|ezurl}>
<input type="hidden" name="NewOwnerID" value="{if $new_owner}{$new_owner.id}{/if}" />
<input type="hidden" name="NodeID" value="{if $node}{$node.node_id}{/if}" />
<input type="hidden" name="PlanFingerprint" value="{$fingerprint|wash}" />
{* Enter in a field previews, never another button. *}
<input type="submit" name="PreviewButton" value="" tabindex="-1" aria-hidden="true" class="a4-sr-only" />

<div class="context-block a4-page">

<div class="box-header"><div class="box-ml">
<div class="a4-pagehead">
    <div class="a4-pagehead-main">
        <p class="a4-pagehead-pre">{if $node}<a href={$back_uri|ezurl}>{$object.name|wash}</a>{else}{$object.name|wash}{/if}</p>
        <h1 class="context-title a4-pagehead-title">{'Change owner'|i18n( $ctx )}</h1>
        <p class="a4-pagehead-sub">{'Current owner: %owner'|i18n( $ctx,, hash( '%owner', first_set( $current_owner.name, '?' ) ) )|wash}</p>
    </div>
</div>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">

{foreach $notices as $notice}
<div class="a4-notice is-{$notice.kind|wash}" role="{if eq( $notice.kind, 'bad' )}alert{else}status{/if}"><p>{$notice.text|wash}</p></div>
{/foreach}

{if $done}
<div class="a4-notice {if $done.failed_count|gt( 0 )}is-bad{else}is-ok{/if}" role="status">
    <p class="a4-notice-title">{if eq( $done.count, 1 )}{'1 owner changed.'|i18n( $ctx )}{else}{'%count owners changed.'|i18n( $ctx,, hash( '%count', $done.count ) )}{/if}</p>
    {if $done.failed_count|gt( 0 )}<p>{'%count objects could not be changed; nothing of their batch was written.'|i18n( $ctx,, hash( '%count', $done.failed_count ) )}</p>{/if}
</div>
{/if}

{if $errors}
<div class="a4-error-summary a4-notice is-bad" role="alert">
    <p class="a4-notice-title">{'Nothing can be changed yet:'|i18n( $ctx )}</p>
    <ul>{foreach $errors as $error}<li>{$error|wash}</li>{/foreach}</ul>
</div>
{/if}

<section class="a4-card" aria-labelledby="ownerchange-choice-title">
    <div class="a4-card-head"><h2 class="a4-card-title a4-h3" id="ownerchange-choice-title">{'What changes'|i18n( $ctx )}</h2></div>
    <div class="a4-card-body a4-stack">
        <dl class="a4-facts is-rows">
            <dt>{'New owner'|i18n( $ctx )}</dt>
            <dd>{if $new_owner}<strong>{$new_owner.name|wash}</strong>{else}<span class="a4-muted">{'None chosen'|i18n( $ctx )}</span>{/if}
                <button type="submit" class="a4-btn a4-btn-small a4-btn-ghost" name="BrowseOwnerButton" value="1">{'Choose another owner'|i18n( $ctx )}</button></dd>
        </dl>

        <fieldset class="a4-field">
            <legend class="a4-label">{'Scope'|i18n( $ctx )}</legend>
            <div class="a4-radios">
                <label class="a4-check"><input type="radio" name="Scope" value="object"{if eq( $scope, 'object' )} checked="checked"{/if} /> {'This object only'|i18n( $ctx )}</label>
                {if and( $node, $can_subtree, $subtree_count|gt( 1 ) )}
                <label class="a4-check"><input type="radio" name="Scope" value="subtree"{if eq( $scope, 'subtree' )} checked="checked"{/if} /> {'This object and everything below it (%count objects)'|i18n( $ctx,, hash( '%count', $subtree_count ) )}</label>
                {/if}
            </div>
            {if and( $node, $can_subtree, $subtree_count|gt( $web_limit ) )}
            <p class="a4-help">{'A subtree of more than %limit objects is changed with the command line script, see the documentation.'|i18n( $ctx,, hash( '%limit', $web_limit ) )}</p>
            {/if}
        </fieldset>

        {if and( $node, $can_subtree, $subtree_count|gt( 1 ), $current_owner )}
        <label class="a4-check"><input type="checkbox" name="OnlyCurrentOwner" value="{$current_owner.id}"{if $only_current_owner|gt( 0 )} checked="checked"{/if} /> {'In a subtree, change only objects owned by %owner'|i18n( $ctx,, hash( '%owner', $current_owner.name ) )|wash}</label>
        {/if}

        {if $publish_new_version}
        <p class="a4-help">{'Each changed object gets a new published version with the new owner as its creator (PublishNewVersion=enabled).'|i18n( $ctx )}</p>
        {/if}
    </div>
    <div class="a4-card-foot a4-actions">
        <button type="submit" class="a4-btn" name="PreviewButton" value="1">{'Update preview'|i18n( $ctx )}</button>
    </div>
</section>

{if $counts}
<section class="a4-section" aria-labelledby="ownerchange-preview-title">
    <div class="a4-section-head">
        <h2 class="a4-h2" id="ownerchange-preview-title">{if $done}{'Result'|i18n( $ctx )}{else}{'Preview: nothing has changed yet'|i18n( $ctx )}{/if}</h2>
    </div>

    <ul class="a4-figures">
        <li class="a4-figure{if and( not( $done ), $counts.change|gt( 0 ) )} is-attention{/if}"><strong>{$counts.change}</strong><span>{if $done}{'Left to change'|i18n( $ctx )}{else}{'Will change'|i18n( $ctx )}{/if}</span></li>
        <li class="a4-figure is-ok"><strong>{$counts.unchanged}</strong><span>{'Already owned by the new owner'|i18n( $ctx )}</span></li>
        <li class="a4-figure"><strong>{$counts.filtered}</strong><span>{'Other owner, left alone'|i18n( $ctx )}</span></li>
        <li class="a4-figure{if $counts.refused|gt( 0 )} is-bad{/if}"><strong>{$counts.refused}</strong><span>{'Refused: you may not edit them'|i18n( $ctx )}</span></li>
    </ul>

    {if $items}
    <div class="a4-table-wrap">
    <table class="a4-table is-compact">
        <caption class="a4-sr-only">{'Objects the change reaches'|i18n( $ctx )}</caption>
        <thead><tr>
            <th scope="col">{'Object'|i18n( $ctx )}</th>
            <th scope="col">{'Current owner'|i18n( $ctx )}</th>
            <th scope="col">{'Outcome'|i18n( $ctx )}</th>
        </tr></thead>
        <tbody>
        {foreach $items as $item}
        <tr>
            <th scope="row">{if $item.node_id|gt( 0 )}<a href={concat( 'content/view/full/', $item.node_id )|ezurl}>{$item.name|wash}</a>{else}{$item.name|wash}{/if} <span class="a4-meta">#{$item.object_id}</span></th>
            <td>{$item.owner_name|wash}</td>
            <td>{switch match=$item.status}
                {case match='change'}<span class="a4-badge is-accent">{'Owner changes'|i18n( $ctx )}</span>{/case}
                {case match='unchanged'}<span class="a4-badge is-ok">{'Unchanged'|i18n( $ctx )}</span>{/case}
                {case match='filtered'}<span class="a4-badge">{'Other owner'|i18n( $ctx )}</span>{/case}
                {case}<span class="a4-badge is-bad">{'Refused'|i18n( $ctx )}</span>{/case}
            {/switch}</td>
        </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
    {if $counts.total|gt( $list_limit )}<p class="a4-meta">{'The first %shown of %count objects are listed.'|i18n( $ctx,, hash( '%shown', $list_limit, '%count', $counts.total ) )}</p>{/if}
    {else}
    <p class="a4-empty">{'The change reaches no object.'|i18n( $ctx )}</p>
    {/if}
</section>
{/if}

<div class="a4-toolbar is-bottom">
    <div class="a4-actions">
        {if and( $counts, $counts.change|gt( 0 ), not( $errors ) )}
        <button type="submit" class="a4-btn a4-btn-primary" name="ConfirmButton" value="1">{if eq( $counts.change, 1 )}{'Change the owner of 1 object'|i18n( $ctx )}{else}{'Change the owner of %count objects'|i18n( $ctx,, hash( '%count', $counts.change ) )}{/if}</button>
        {/if}
        {if $done}
        <a class="a4-btn" href={$back_uri|ezurl}>{'Back to the object'|i18n( $ctx )}</a>
        {else}
        <button type="submit" class="a4-btn a4-btn-ghost" name="CancelButton" value="1">{'Cancel'|i18n( $ctx )}</button>
        {/if}
    </div>
</div>

</div></div></div>

</div>
</form>

{undef $ctx}
