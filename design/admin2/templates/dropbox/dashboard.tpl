<div class="context-block dropbox-dashboard">
	<div class="box-header">
		<h1 class="context-title"><a href={'dropbox/dashboard/'|ezurl}>Dropbox</a>
        {if $parent} - <a href={concat('dropbox/dashboard/', $parent.id)|ezurl}>{$parent.path}</a>{/if}</h1>
		<div class="header-mainline"></div>
	</div>

	<div class="box-content">

        <table class="list">
        <tr>
            <th>Title</th>
            <th>Dropbox Path</th>
            <th>Modified</th>
            <th>Imported in node</th>
            <th>Class Identifier</th>
        </tr>
        {def $object = false()
             $node = false()}
        {foreach $list as $t}
        {set $object = fetch( 'content', 'object', hash( 'object_id', $t.object_id))}            
        {if $parent|not()}
            {if $object}
            <tr>
                <td>
                    {if $t.is_dir|gt(0)}
                        <a href={concat('dropbox/dashboard/', $t.id)|ezurl}>{$t.name|wash()}</a>
                    {else}
                        {$t.name|wash()}
                    {/if}
                </td>
                <td>{$t.path}</td>
                <td>{$t.modified|l10n(datetime)}</td>            
                {if $object}
                    {set $node = $object.main_node}
                    <td><a href={$node.url_alias|ezurl}>{$node.name|wash()}</a></td>
                    <td>{$node.class_identifier}</td>   
                {else}
                    <td colspan="2">...not found!</td>
                {/if}
            </tr>
            {/if}
        {else}
            <tr>
                <td>
                    {if $t.is_dir|gt(0)}
                        <a href={concat('dropbox/dashboard/', $t.id)|ezurl}>{$t.name|wash()}</a>
                    {else}
                        {$t.name|wash()}
                    {/if}
                </td>
                <td>{$t.path}</td>
                <td>{$t.modified|l10n(datetime)}</td>            
                {if $object}
                    {set $node = $object.main_node}
                    <td><a href={$node.url_alias|ezurl}>{$node.name|wash()}</a></td>
                    <td>{$node.class_identifier}</td>   
                {else}
                    <td colspan="2">...not found!</td>
                {/if}
            </tr>        
        {/if}
        {/foreach}
        </table>				
                
	</div>
</div>