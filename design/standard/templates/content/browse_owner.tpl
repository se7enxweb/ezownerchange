{* The description above the content browser while a new owner is chosen (owner/change), outside the
   administration designs. *}
{def $content_object = fetch( 'content', 'object', hash( 'object_id', $browse.content.object_id ) )}
<div class="maincontentheader">
<h1>{'Choose the new owner of %object_name'|i18n( 'extension/ezownerchange',, hash( '%object_name', $content_object.name ) )|wash}</h1>
</div>
<p>{'Select a user and click "Select". Nothing changes yet: the next page shows what the change reaches, and you confirm it there.'|i18n( 'extension/ezownerchange' )}</p>
{undef $content_object}
