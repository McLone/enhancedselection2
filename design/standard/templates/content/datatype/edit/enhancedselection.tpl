{let content=$attribute.content
     classContent=$attribute.class_content
     id=$attribute.id
     i18n_context="extension/enhancedselection/object/edit"
     available_options=$classContent.options}

<select name="ContentObjectAttribute_enhancedselection_selection_{$id}[]"
        {section show=$classContent.is_multiselect}multiple="multiple"{/section}>
        
    {section var=option loop=$available_options}
        <option value="{$option.item.identifier|wash}"
                {section show=$content|contains($option.item.identifier)}selected="selected"{/section}>
            {$option.item.name|wash}
        </option>
    {/section}      
        
</select>
     
{/let}     