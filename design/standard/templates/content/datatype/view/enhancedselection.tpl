{let content=$attribute.content
     classContent=$attribute.class_content
     i18n_context="extension/enhancedselection/object/view"
     available_options=$classContent.options}

{section var=option loop=$available_options}{section-exclude match=$content|contains($option.item.identifier)|not}{$option.item.name|wash}{delimiter}{cond($classContent.delimiter|ne(""),$classContent.delimiter,", ")}{/delimiter}{/section}

{/let}