{if $attribute|get_class|eq('ezinformationcollectionattribute')}
    {def $selected_id_array=$attribute.data_text|explode( cond( $attribute.class_content.delimiter, $attribute.class_content.delimiter, ', ' ) )}
    {foreach $attribute.class_content.options as $option}
        {if $selected_id_array|contains( $option.identifier )|not}{continue}{/if}
        {delimiter}{$attribute.class_content.delimiter}{/delimiter}    
        {$option.name|wash( xhtml )}
    {/foreach}
    {undef $selected_id_array}
{/if}