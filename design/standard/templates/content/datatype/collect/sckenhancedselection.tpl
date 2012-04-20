{let classContent=$attribute.class_content
     available_options=$classContent.options
     id=$attribute.id}
{def $selected_id_array=cond( is_set( $#collection_attributes[$attribute.id] ), $#collection_attributes[$attribute.id].data_text, '' )|explode( cond( $classContent.delimiter, $classContent.delimiter, ', ' ) )}
{section show=and(is_set($classContent.db_options),count($classContent.db_options)|gt(0))}
    {set available_options=$classContent.db_options}
{/section}

<select name="ContentObjectAttribute_sckenhancedselection_selection_{$id}[]"
        {section show=$classContent.is_multiselect}multiple="multiple"{/section}>
        
    {section var=option loop=$available_options}
        <option value="{$option.item.identifier|wash}"{if $selected_id_array|contains($option.item.identifier)} selected="selected"{/if}>
            {$option.item.name|wash}
        </option>
    {/section}      
        
</select>  

{/let}