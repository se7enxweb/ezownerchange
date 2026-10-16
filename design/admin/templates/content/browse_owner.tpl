{* The description above the content browser while a new owner is chosen (owner/change, browse.ini [ChangeOwner]).
   Shared administration components only; no CSS of its own. *}
{def $ctx = 'extension/ezownerchange'
     $content_object = fetch( 'content', 'object', hash( 'object_id', $browse.content.object_id ) )}

<div class="context-block a4-page">

<div class="box-header"><div class="box-ml">
<div class="a4-pagehead">
    <div class="a4-pagehead-main">
        <p class="a4-pagehead-pre">{'Change owner'|i18n( $ctx )}</p>
        <h1 class="context-title a4-pagehead-title">{'Choose the new owner of %object_name'|i18n( $ctx,, hash( '%object_name', $content_object.name ) )|wash}</h1>
    </div>
</div>
</div></div>

<div class="box-bc"><div class="box-ml"><div class="box-content">
<p class="a4-intro">{'Select a user and click "Select". Nothing changes yet: the next page shows what the change reaches, and you confirm it there.'|i18n( $ctx )}</p>
<p class="a4-help">{'Navigate using the available tabs (above), the tree menu (left) and the content list (middle).'|i18n( $ctx )}</p>
</div></div></div>

</div>

{undef $ctx $content_object}
