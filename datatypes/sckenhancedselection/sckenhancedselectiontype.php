<?php

/*
    Enhanced Selection extension for eZ publish 4.x
    Copyright (C) 2003-2008  SCK-CEN (Belgian Nuclear Research Centre)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
*/


/*!
  \class   SckEnhancedSelectionType sckenhancedselectiontype.php
  \ingroup eZDatatype
  \brief   Handles the datatype sckenhancedselection.
  \version 3.0
  \date    Tuesday 16 August 2005 9:56:00 am
  \author  Original Hans Melis, ported to PHP5 by Tom Couwberghs
*/

include_once( 'kernel/common/i18n.php' );

class SckEnhancedSelectionType extends eZDataType
{
    const DATATYPESTRING = 'sckenhancedselection';
    const CLASS_STORAGE_XML = 'data_text5';

    function SckEnhancedSelectionType()
    {
        $this->eZDataType( self::DATATYPESTRING,
                           ezpI18n::tr( 'extension/sckenhancedselection/datatypes', 'Enhanced Selection 2', 'Datatype name' ),
                           array( 'serialize_supported' => true,
                                  'object_serialize_map' => array( 'data_text' => 'selection' )
                                )
                         );
    }

/********
* CLASS *
********/

    function validateClassAttributeHTTPInput( $http, $base, $classAttribute )
    {
        $id = $classAttribute->attribute( 'id' );
        $queryName = join( '_', array( $base, 'sckenhancedselection_query', $id ) );

        if( $http->hasPostvariable( $queryName ) )
        {
            $query = trim( $http->postVariable( $queryName ) );

            if( !empty( $query ) )
            {
                if( $this->isDbQueryValid( $query ) !== true )
                {
                    return eZInputValidator::STATE_INVALID;
                }
            }
        }

        return eZInputValidator::STATE_ACCEPTED;
    }

    function fetchClassAttributeHTTPInput( $http, $base, $classAttribute )
    {
        $content = $classAttribute->content();
        $id = $classAttribute->attribute( 'id' );

        $idArrayName = join( '_', array( $base, 'sckenhancedselection_id', $id ) );
        $nameArrayName = join( '_', array( $base, 'sckenhancedselection_name', $id ) );
        $identifierArrayName = join( '_', array( $base, 'sckenhancedselection_identifier', $id ) );
        $priorityArrayName = join( '_', array( $base, 'sckenhancedselection_priority', $id ) );

        $multiSelectName = join( '_', array( $base, 'sckenhancedselection_multi', $id ) );
        $delimiterName = join( '_', array( $base, 'sckenhancedselection_delimiter', $id ) );

        $queryName = join( '_', array( $base, 'sckenhancedselection_query', $id ) );

        if( $http->hasPostVariable( $idArrayName ) )
        {
            $idArray = $http->postVariable( $idArrayName );
            $nameArray = $http->postVariable( $nameArrayName );
            $identifierArray = $http->postVariable( $identifierArrayName );
            $priorityArray = $http->postVariable( $priorityArrayName );

            foreach( $idArray as $index => $id )
            {
                $identifier = $identifierArray[$id];

                if( empty( $identifier ) )
                {
                    $identifier = $this->generateIdentifier( $nameArray[$id], $identifierArray );
                }

                $content['options'][$index] = array( 'id' => $id,
                                                     'name' => $nameArray[$id],
                                                     'identifier' => $identifier,
                                                     'priority' => $priorityArray[$id] );
            }
        }

        if( $http->hasPostVariable( $multiSelectName ) )
        {
            $content['is_multiselect'] = 1;
        }
        else if( $http->hasPostVariable( 'ContentClassHasInput' ) )
        {
            $content['is_multiselect'] = 0;
        }

        if( $http->hasPostVariable( $delimiterName ) )
        {
            $content['delimiter'] = $http->postVariable( $delimiterName );
        }

        if( $http->hasPostVariable( $queryName ) )
        {
            $content['query'] = trim( $http->postVariable( $queryName ) );
        }

        $classAttribute->setContent( $content );
        $classAttribute->store();

        return true;
    }

    function classAttributeContent( $classAttribute )
    {
        $xmlString = $classAttribute->attribute( self::CLASS_STORAGE_XML );
        $content = array();

        $this->xmlToClassContent( $xmlString, $content, $classAttribute );

        $content['db_options'] = $this->getDbOptions( $content );

        $queryName = join( '_', array( 'ContentClass_sckenhancedselection_query', $classAttribute->attribute( 'id' ) ) );
        $http = eZHTTPTool::instance();

        if( empty( $content['query'] ) and
            $http->hasPostVariable( $queryName ) )
        {
            $query = $http->postVariable( $queryName );
            $content['query'] = $query;
        }

        return $content;
    }

    function storeClassAttribute( $classAttribute, $version )
    {
        $content = $classAttribute->content();

        unset( $content['db_options'] ); // Make sure this can never slip into the database

        $xmlString = $this->classContentToXml( $content, $classAttribute );

        $classAttribute->setAttribute( self::CLASS_STORAGE_XML, $xmlString );
    }

    function customClassAttributeHTTPAction( $http, $action, $classAttribute )
    {
        $id = $classAttribute->attribute( 'id' );
        $base = "ContentClass";
        $content = $classAttribute->content();

        $customActionVarName = "CustomActionButton";
        $customActionKeyName = "{$id}_{$action}";

        $idArrayName = join( '_', array( $base, 'sckenhancedselection_id', $id ) );
        $idArray = array();

        if( $http->hasPostVariable( $idArrayName ) )
        {
            $idArray = $http->postVariable( $idArrayName );
        }

        switch( $action )
        {
            case 'new_option':
            {
                $maxID = 0;
                foreach( $content['options'] as $option )
                {
                    if( intval( $option['id'] ) > $maxID )
                    {
                        $maxID = intval( $option['id'] );
                    }
                }
                $maxID++;

                $content['options'][] = array( 'id' => $maxID,
                                               'name' => '',
                                               'identifier' => '',
                                               'priority' => 1 );
            } break;

            case 'remove_optionlist':
            {
                $removeArrayName = join( '_', array( $base, "sckenhancedselection_remove", $id ) );

                if( $http->hasPostVariable( $removeArrayName ) )
                {
                    $removeArray = $http->postVariable( $removeArrayName );

                    foreach( $removeArray as $removeID )
                    {
                        unset( $idArray[$removeID] );
                        unset( $content['options'][$removeID] );
                    }
                }
            } break;

            case 'move_up':
            {
                $customActionVar = $http->postVariable( $customActionVarName );
                $customActionValue = $customActionVar[$customActionKeyName]; // This is where the user clicked

                // Up == swap selected row with the one above
                // Or: Move the row above below the selected one
                $this->swapRows( $customActionValue - 1, $customActionValue, $content, $idArray );
            } break;

            case 'move_down':
            {
                $customActionVar = $http->postVariable( $customActionVarName );
                $customActionValue = $customActionVar[$customActionKeyName]; // This is where the user clicked

                // Down == swap selected row with the one below
                // Or: Move the selected row below the one below
                $this->swapRows( $customActionValue, $customActionValue + 1, $content, $idArray );

            } break;

            case 'sort_optionlist':
            {
                $sortName = join( '_', array( $base, 'sckenhancedselection_sort_order', $id ) );

                if( $http->hasPostVariable( $sortName ) )
                {
                    $sort = $http->postVariable( $sortName );
                    $sortArray = array();
                    $sortOrder = SORT_ASC;
                    $sortType = SORT_STRING;
                    $numericSorts = array( 'prior' );

                    if( strpos( $sort, '_' ) !== false )
                    {
                        list( $type, $ranking ) = explode( '_', $sort );
                        $currentOptions = $content['options'];

                        switch( $ranking )
                        {
                            case 'desc':
                                $sortOrder = SORT_DESC;
                                break;

                            case 'asc':
                            default:
                                $sortOrder = SORT_ASC;
                                break;
                        }

                        if( in_array( $type, $numericSorts ) )
                        {
                            $sortType = SORT_NUMERIC;
                        }

                        // Use POST priorities instead of the stored ones
                        // Otherwise you have to store new priorities before you can sort
                        $priorityArray = array();
                        if( $type == 'prior' )
                        {
                            $priorityArray = $http->postVariable( join( '_', array( $base, 'sckenhancedselection_priority', $id ) ) );
                        }

                        foreach( array_keys( $currentOptions ) as $key )
                        {
                            $option = $currentOptions[$key];

                            switch( $type )
                            {
                                case 'prior':
                                    if( isset( $priorityArray[$option['id']] ) )
                                    {
                                        $option['priority'] = $priorityArray[$option['id']];
                                    }
                                    $sortArray[] = $option['priority'];
                                    break;

                                case 'alpha':
                                default:
                                    $sortArray[] = $option['name'];
                                    break;
                            }

                            unset( $option );
                        }

                        array_multisort( $sortArray, $sortOrder, $sortType, $currentOptions );

                        $idArray = array();
                        foreach( $currentOptions as $option )
                        {
                            $idArray[] = $option['id'];
                        }

                        $content['options'] = $currentOptions;
                    }
                    else
                    {
                        eZDebug::writeError( "Unknown sort value. Please use the form type_order (ex. alpha_asc)", "SckEnhancedSelectionType" );
                    }
                }

            } break;

            default:
            {
                eZDebug::writeError( "Unknown class HTTP action: $action", "SckEnhancedSelectionType" );
            }
        }

        $classAttribute->setContent( $content );
        $classAttribute->store();

        $http->setPostVariable( $idArrayName, $idArray );
    }

/*********
* OBJECT *
*********/

    function validateObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $status = $this->validateAttributeHTTPInput( $http, $base, $contentObjectAttribute, false );

        return $status;
    }

    function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $id = $contentObjectAttribute->attribute( 'id' );
        $classContent = $contentObjectAttribute->classContent();
        $content = $contentObjectAttribute->content();

        $selectionName = join( '_', array( $base, 'sckenhancedselection_selection', $id ) );

        if( $http->hasPostVariable( $selectionName ) )
        {
            $selection = $http->postVariable( $selectionName );

            $content = $selection;
        }
        else if( $classContent['is_multiselect'] == 1 )
        {
            $content = array();
        }

        $contentObjectAttribute->setContent( $content );

        return true;
    }

    function objectAttributeContent( $contentObjectAttribute )
    {
        $content = array();
        $contentString = $contentObjectAttribute->attribute( 'data_text' );

        if( !empty( $contentString ) )
        {
            $content = unserialize( $contentString );
        }

        return $content;
    }

    function hasObjectAttributeContent( $contentObjectAttribute )
    {
        $contentString = $contentObjectAttribute->attribute( 'data_text' );

        if( empty( $contentString ) )
        {
            return false;
        }

        $selection = unserialize( $contentString );

        if( !is_array( $selection ) or count( $selection ) == 0 )
        {
            return false;
        }

        return true;
    }

    function storeObjectAttribute( $objectAttribute )
    {
        $content = $objectAttribute->content();

        $contentString = serialize( $content );

        $objectAttribute->setAttribute( 'data_text', $contentString );
    }

    function customObjectAttributeHTTPAction( $http, $action, $objectAttribute, $parameters )
    {
    }

/*************
* COLLECTION *
*************/

    function validateCollectionAttributeHTTPInput( $http, $base, $objectAttribute )
    {
        $status = $this->validateAttributeHTTPInput( $http, $base, $objectAttribute, true );

        return $status;
    }

    function fetchCollectionAttributeHTTPInput( $collection, $collectionAttribute, $http, $base, $objectAttribute )
    {
        $id = $objectAttribute->attribute( 'id' );
        $classContent = $objectAttribute->classContent();
        $content = $objectAttribute->content();
        $nameArray = array();

        $selectionName = join( '_', array( $base, 'sckenhancedselection_selection', $id ) );
        $selection = $http->postVariable( $selectionName );

        if( $http->hasPostVariable( $selectionName ) )
        {
            $selection = $http->postVariable( $selectionName );

            if( count( $selection ) > 0 )
            {
                $options = $classContent['options'];

                if( isset( $classContent['db_options'] ) and count( $classContent['db_options'] ) > 0 )
                {
                    unset( $options );
                    $options = $classContent['db_options'];
                }

                foreach( $options as $option )
                {
                    if( in_array( $option['identifier'], $selection ) )
                    {
                        $nameArray[] = $option['name'];
                    }
                }

                unset( $options );
            }
        }

        $delimiter = $classContent['delimiter'];

        if( empty( $delimiter ) )
        {
            $delimiter = ', ';
        }

        $dataText = join( $delimiter, $nameArray );

        $collectionAttribute->setAttribute( 'data_text', $dataText );

        return true;
    }

    function hasInformationCollection()
    {
        return false;
    }

/**********
* GENERAL *
**********/

    function metaData( $contentObjectAttribute )
    {
        $content = $contentObjectAttribute->content();
        $classContent = $contentObjectAttribute->classContent();

        if( count( $content ) > 0 )
        {
            $metaDataArray = array();
            $options = $classContent['options'];

            if( isset( $classContent['db_options'] ) and count( $classContent['db_options'] ) > 0 )
            {
                unset( $options );
                $options = $classContent['db_options'];
            }

            foreach( $options as $option )
            {
                if( in_array( $option['identifier'], $content ) )
                {
                    $metaDataArray[] = array( 'id' => '',
                                              'text' => $option['identifier'] );
                    $metaDataArray[] = array( 'id' => '',
                                              'text' => $option['name'] );
                }
            }

            unset( $options );

            return $metaDataArray;
        }

        return "";
    }

    function title( $contentObjectAttribute, $name = null )
    {
        $content = $contentObjectAttribute->content();
        $classContent = $contentObjectAttribute->classContent();
        $titleArray = array();
        $titleString = "";

        if( count( $content ) > 0 )
        {
            $options = $classContent['options'];

            if( isset( $classContent['db_options'] ) and count( $classContent['db_options'] ) > 0 )
            {
                unset( $options );
                $options = $classContent['db_options'];
            }

            foreach( $options as $option )
            {
                if( in_array( $option['identifier'], $content ) )
                {
                    $titleArray[] = $option['name'];
                }
            }

            unset( $options );
        }

        if( count( $titleArray ) > 0 )
        {
            $delimiter = $classContent['delimiter'];

            if( empty( $delimiter ) )
            {
                $delimiter = ", ";
            }

            $titleString = join( $delimiter, $titleArray );
        }

        return $titleString;
    }

    function isIndexable()
    {
        return true;
    }

    function isInformationCollector()
    {
        return true;
    }

    function sortKey( $objectAttribute )
    {
        $content = $objectAttribute->content();
        $contentString = join(' ', $content);
        $contentString = strtolower( $contentString );

        return $contentString;
    }

    function sortKeyType()
    {
        return 'string';
    }

    function serializeContentClassAttribute( $classAttribute, $attributeNode, $attributeParametersNode )
    {
        $content = array();
        $this->xmlToClassContent( $classAttribute->attribute( self::CLASS_STORAGE_XML ), $content, $classAttribute, true );

        $dom = $attributeParametersNode->ownerDocument;
        $optionsNode = $dom->createElement('options');

        if( is_array( $content['options'] ) and count( $content['options'] ) > 0 )
        {
            foreach( $content['options'] as $option )
            {
                $optionNode = $dom->createElement('option');

                $optionNode->setAttribute( 'id', $option['id']  );
                $optionNode->setAttribute( 'identifier', $option['identifier']);
                $optionNode->setAttribute( 'priority', $option['priority']  );
                
                foreach( $option['name'] as $nameLang => $nameValue )
                {
                    $nameNode = $dom->createElement( 'name', $nameValue );
                    $nameNode->setAttribute( 'lang', $nameLang );
                    $optionNode->appendChild( $nameNode );
                }
                
                $optionsNode->appendChild( $optionNode );

                unset( $optionNode );
            }
        }

        $delimiterElement = $dom->createElement('delimiter');
        $delimiterElement->appendChild( $dom->createCDATASection( $content['delimiter'] ) );
        $attributeParametersNode->appendChild( $delimiterElement );
        $attributeParametersNode->appendChild( $dom->createElement( 'multiselect', $content['is_multiselect'] ) );
        foreach ( $content['query'] as $queryLang => $queryValue )
        {
            $queryElement = $dom->createElement('query');
            $queryElement->setAttribute( 'lang', $queryLang );
            $queryElement->appendChild( $dom->createCDATASection( $queryValue ) );
            $attributeParametersNode->appendChild( $queryElement );
        }
        $attributeParametersNode->appendChild( $optionsNode );

        unset( $optionsNode );
    }

    function unserializeContentClassAttribute( $classAttribute, $attributeNode, $attributeParametersNode )
    {
        $content = array();

        $initialLanguage = $this->initialLocale( $classAttribute );
        
        $delimiter = $attributeParametersNode->getElementsByTagName( 'delimiter' )->item(0)->nodeValue;
        $multiselect = $attributeParametersNode->getElementsByTagName( 'multiselect' )->item(0)->textContent;
        $query = array();
        foreach( $attributeParametersNode->getElementsByTagName( 'query' ) as $queryNode )
        {
            $query[$queryNode->hasAttribute( 'lang' ) ? $queryNode->getAttribute( 'lang' ) : $initialLanguage ] = $queryNode->nodeValue;
        }

        $content['delimiter'] = $delimiter !== false ? $delimiter : '';
        $content['is_multiselect'] = $multiselect !== false ? intval( $multiselect ) : 0;
        $content['query'] = $query !== false ? $query : array();
        $content['options'] = array();

        $optionsNode = $attributeParametersNode->getElementsByTagName( 'options' )->item(0);

        $dom = $optionsNode->ownerDocument;



        if( $optionsNode instanceof DomElement && $optionsNode->hasChildNodes() === true )
        {
            $children = $optionsNode->childNodes;

            foreach( $children as $key => $child )
            {
                if( $child instanceof DomElement)
                {
                $name = array();
                $nameNodeList = $child->getElementsByTagName( 'name' );
                if ( $nameNodeList->length == 0 ) //Used only for backcompatibility with pre-multilanguage release content
                {
                    $name[$initialLanguage] = $child->getAttribute( 'name' );
                }
                else
                {
                    foreach( $nameNodeList as $nameNode )
                    {
                        $name[$nameNode->getAttribute( 'lang' )] = $nameNode->textContent;
                    }
                }
                $content['options'][] = array( 'id' => $child->getAttribute('id'),
                                               'name' => $name,
                                               'identifier' => $child->getAttribute( 'identifier' ),
                                               'priority' => $child->getAttribute( 'priority' ) );
                }
            }
        }

        unset( $optionsNode );

        $xmlString = $this->classContentToXml( $content, $classAttribute, true );

        $classAttribute->setAttribute( self::CLASS_STORAGE_XML, $xmlString );
    }

/**********
* HELPERS *
**********/

    /**
     * Convert a content given as an associative array to an XML for our class storage
     * @param array $content
     * @param eZContentClassAttribute $classAttribute 
     * @param [optional]bool $multiLanguage   If set to true, the content array contains all language for its "name" 
     *                                        and "query" fields (as an associative array : 'language code' => 'value'),
     *                                        the previously stored content is ignored and replaced by our content array
     *                                        ; if set to false (default), content only contains current language for 
     *                                        this fields, content array is merged to the previously stored content, 
     *                                        allowing to keep other translation stored.
     * @return string
     */
    function classContentToXml( $content, $classAttribute, $multiLanguage = false )
    {
        //Getting previous content, to eventually merge other language names
        //We use the id field of each option to link all language names, not the identifier field wich can be modified anytime
        $previousMultiLanguageContent = array();
        $multiLanguageNameById = array();
        if ( !$multiLanguage ) //Actually, it's unnecessary for the multilanguage case, as we overwrite it anyway
        {
            $this->xmlToClassContent( $classAttribute->attribute( self::CLASS_STORAGE_XML ), $previousMultiLanguageContent, $classAttribute, true );
            if ( isset( $previousMultiLanguageContent['options'] ) )
            {
                $multiLanguageNameById = self::getMultiLanguageNameById( $previousMultiLanguageContent['options'] );
            }
        }
        
        $currentLocale = $this->currentLocale( $classAttribute );
        
        $doc = new DOMDocument();
        $root = $doc->createElement( 'content' );

        $optionsNode = $doc->createElement( 'options' );

        if( isset( $content['options'] ) and count( $content['options'] ) > 0 )
        {
            foreach( $content['options'] as $option )
            {
                $optionNode = $doc->createElement( 'option' );
                
                if ( $multiLanguage )
                {
                    $nameByLangList = $option['name'];
                }
                else
                {
                    $nameByLangList = isset( $multiLanguageNameById[$option['id']] ) ? array_merge( $multiLanguageNameById[$option['id']], array( $currentLocale => $option['name'] ) ) : array( $currentLocale => $option['name'] );
                }
                foreach( $nameByLangList as $nameLang => $nameValue )
                {
                    $nameNode = $doc->createElement( 'name', $nameValue);
                    $nameNode->setAttribute( 'lang', $nameLang );
                    $optionNode->appendChild( $nameNode );
                }

                $optionNode->setAttribute( 'id', $option['id'] );
                $optionNode->setAttribute( 'identifier', $option['identifier']);
                $optionNode->setAttribute( 'priority', $option['priority'] );

                $optionsNode->appendChild( $optionNode );

                unset( $optionNode );
            }
        }

        $root->appendChild( $optionsNode );


        // Multiselect
        if( isset( $content['is_multiselect'] ) )
        {
            $multiSelectNode = $doc->createElement( 'multiselect', $content['is_multiselect'] );
            $root->appendChild( $multiSelectNode );
        }

        // Delimiter
        if( isset( $content['delimiter'] ) )
        {
            $delimiterElement = $doc->createElement('delimiter');
            $delimiterElement->appendChild( $doc->createCDATASection( $content['delimiter'] ) );
            $root->appendChild( $delimiterElement );
        }

        // DB Query
        if( isset( $content['query'] ) )
        {
            if ( $multiLanguage )
            {
                $queryByLangList = $content['query'];
            }
            else
            {
                $queryByLangList = ( isset( $previousMultiLanguageContent['query'] ) && is_array( $previousMultiLanguageContent['query'] ) ) ? array_merge( $previousMultiLanguageContent['query'], array( $currentLocale => $content['query'] ) ) : array( $currentLocale => $content['query'] );
            }
            foreach( $queryByLangList as $queryLang => $queryValue )
            {
                $queryElement = $doc->createElement('query');
                $queryElement->setAttribute( 'lang', $queryLang );
                $queryElement->appendChild( $doc->createCDATASection( $queryValue ) );
                $root->appendChild( $queryElement );
            }
        }

        $doc->appendChild( $root );

        $xml = $doc->saveXML();

        return $xml;
    }

    function xmlToClassContent( $xmlString, &$content, $classAttribute, $multiLanguage = false )
    {
        if( $xmlString != '')
        {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->loadXML( $xmlString );

            if( $dom )
            {
                $currentLocale = $this->currentLocale( $classAttribute );
                $initialLocale = $this->initialLocale( $classAttribute );
                $optionsNode = $dom->getElementsByTagName( 'options' )->item(0);
                $content['options'] = array();

                if( $optionsNode instanceof DomElement && $optionsNode->hasChildNodes()== true )
                {
                    $children = $optionsNode->childNodes;

                    foreach( $children as $child )
                    {
                        $name = '';
                        $nameNodeList = $child->getElementsByTagName( 'name' );
                        if ( $nameNodeList->length > 0 )
                        {
                            $initialLocaleName = '';
                            $foundCurrentLocale = false;
                            foreach( $nameNodeList as $subChild )
                            {
                                if ( $multiLanguage )
                                {
                                    if ( empty( $name ) )
                                    {
                                        $name = array();
                                    }
                                    $name[$subChild->getAttribute( 'lang' )] = $subChild->textContent;
                                }
                                else
                                {
                                    if ( $subChild->getAttribute( 'lang' ) == $currentLocale )
                                    {
                                        $foundCurrentLocale = true;
                                        $name = $subChild->textContent;
                                        break;
                                    }
                                    if ( $subChild->getAttribute( 'lang' ) == $initialLocale )
                                    {
                                        $initialLocaleName = $subChild->textContent;
                                    }
                                }
                            }
                            if ( !$multiLanguage && !$foundCurrentLocale )
                            {
                                $name = $initialLocaleName;
                            }
                        }
                        else //Used only for backcompatibility with pre-multilanguage release content
                        {
                            $name = $child->getAttribute( 'name' );
                            if ( $multiLanguage )
                            {
                                $name = array( $initialLocale => $child->getAttribute( 'name' ) );
                            }
                        }
                        
                        $content['options'][] = array( 'id' => $child->getAttribute( 'id' ),
                                                       'name' => $name,
                                                       'identifier' => $child->getAttribute( 'identifier' ),
                                                       'priority' => $child->getAttribute( 'priority' ) );
                    }
                }

                $multiSelectNode = $dom->getElementsByTagName( 'multiselect' )->item(0);
                $content['is_multiselect'] = 0;

                if( $multiSelectNode instanceof DomElement )
                {
                    $content['is_multiselect'] = intval( $multiSelectNode->textContent );
                }

                $delimiterNode = $dom->getElementsByTagName( 'delimiter' )->item(0);
                $content['delimiter'] = '';

                if( $delimiterNode instanceof DomElement )
                {
                    $content['delimiter'] = $delimiterNode->nodeValue;
                }

                $content['query'] = '';
                $initialLocaleQuery = '';
                $foundCurrentLocale = false;
                foreach( $dom->getElementsByTagName( 'query' ) as $queryNode )
                {
                    if ( $multiLanguage )
                    {
                        $content['query'][$queryNode->hasAttribute( 'lang' ) ? $queryNode->getAttribute( 'lang' ) : $initialLocale] = $queryNode->nodeValue;
                    }
                    else
                    {
                        if ( $queryNode->getAttribute( 'lang' ) == $currentLocale )
                        {
                            $foundCurrentLocale = true;
                            $content['query'] = $queryNode->nodeValue;
                            break;
                        }
                        if ( !$queryNode->hasAttribute( 'lang' ) || $queryNode->getAttribute( 'lang' ) == $initialLocale )
                        {
                            $initialLocaleQuery = $queryNode->nodeValue;
                        }
                    }
                }
                if ( !$multiLanguage && !$foundCurrentLocale )
                {
                    $content['query'] = $initialLocaleQuery;
                }
            }
            else
            {
                $content['options'] = array();
                $content['is_multiselect'] = 0;
                $content['delimiter'] = '';
                $content['query'] = '';
            }
        }
    }

    function generateIdentifier( $name, $identifierArray = array() )
    {
        if( empty( $name ) )
        {
            return '';
        }

        $identifier = $name;

        $trans = eZCharTransform::instance();
        $generatedIdentifier = $trans->transformByGroup( $identifier, 'identifier' );


        // We have $generatedIdentifier now, check for existance
        if( is_array( $identifierArray ) and
            count( $identifierArray ) > 0 and
            in_array( $generatedIdentifier, $identifierArray ) )
        {
            $highestNumber = 0;

            foreach( $identifierArray as $ident )
            {
                if( preg_match( '/^' . $generatedIdentifier . '__(\d+)$/', $ident, $matchArray ) )
                {
                    if( $matchArray[1] > $highestNumber )
                    {
                        $highestNumber = $matchArray[1];
                    }
                }
            }

            $generatedIdentifier .= "__" . ++$highestNumber;
        }

        return $generatedIdentifier;
    }

    /**
    * \param[in] $attribID The attribute ID used to make the custom action unique (class or object level)
    * \param[in] $http Instance of the eZHTTPTool class
    * \param[in] $action The name of the action if you want to check for a specific action
    * \retval boolean \c true if the custom action has fired; \c false if it hasn't
    * \brief Checks if a custom action ( combination of \a $attribID and \a $action ) has fired
    */
    function hasCustomAction( $attribID, $http, $action = false )
    {
        if( $http->hasPostVariable( 'CustomActionButton' ) )
        {
            $keys = array_keys( $http->postVariable( 'CustomActionButton' ) );

            if( $action !== false )
            {
                $attribID .= "_$action";
            }

            foreach( $keys as $key )
            {
                if( strpos( $key, "$attribID" ) === 0 ) // Begins with the attribID
                {
                    return true;
                }
            }
        }

        return false;
    }

    function swapRows( $highest, $lowest, &$content, &$postVar )
    {
        if( isset( $content['options'][$highest] ) and isset( $content['options'][$lowest] ) )
        {
            // Ok to proceed
            $tmp = $content['options'][$highest];
            $content['options'][$highest] = $content['options'][$lowest];
            $content['options'][$lowest] = $tmp;

            // Make sure the post var follows
            $tmp = $postVar[$highest];
            $postVar[$highest] = $postVar[$lowest];
            $postVar[$lowest] = $tmp;
        }
    }

    function isDbQueryValid( $sql )
    {
        $db = eZDB::instance();
        $isValid = false;

        $res = $db->arrayQuery( $sql, array( 'limit' => 1 ) );

        if( is_array( $res ) and count( $res ) == 1 )
        {
            if( isset( $res[0]['name'] ) and isset( $res[0]['identifier'] ) )
            {
                $isValid = true;
            }
        }

        return $isValid;
    }

    function getDbOptions( $classContent )
    {
        $ret = array();

        if( isset( $classContent['query'] ) and
            !empty( $classContent['query'] ) and
            $this->isDbQueryValid( $classContent['query'] ) === true )
        {
            $db = eZDB::instance();
            $res = $db->arrayQuery( $classContent['query'] );

            if( is_array( $res ) and count( $res ) > 0 )
            {
                if( $classContent['is_multiselect'] == 0 )
                {
                    $ret = array_merge( array( array( 'name' => '', 'identifier' => '' ) ), $res );
                }
                else
                {
                    $ret = $res;
                }
            }
        }

        return $ret;
    }

    function validateAttributeHTTPInput( $http, $base, $contentObjectAttribute, $isInformationCollection = false )
    {
        $id = $contentObjectAttribute->attribute( 'id' );
        $classAttribute = $contentObjectAttribute->contentClassAttribute();
        $classContent = $classAttribute->content();
        $isRequired = false;
        $infoCollectionCheck = ( $isInformationCollection == $classAttribute->attribute( 'is_information_collector' ) );

        $isRequired = $contentObjectAttribute->validateIsRequired();

        $selectionName = join( '_', array( $base, 'sckenhancedselection_selection', $id ) );

        if( $http->hasPostVariable( $selectionName ) )
        {
            $selection = $http->postVariable( $selectionName );

            if( $infoCollectionCheck === true )
            {
                switch( true )
                {
                    case $isRequired === true and count( $selection ) == 0:
                    case $isRequired === true and count( $selection ) == 1 and empty( $selection[0] ):
                    {
                        $contentObjectAttribute->setValidationError( ezpI18n::tr( 'extension/sckenhancedselection/datatypes',
                                                                             'This is a required field.' )
                                                                   );
                        return eZInputValidator::STATE_INVALID;
                    } break;
                }
            }
        }
        else
        {
            if( $infoCollectionCheck === true and $isRequired === true and $classContent['is_multiselect'] == 1 )
            {
                $contentObjectAttribute->setValidationError( ezpI18n::tr( 'extension/sckenhancedselection/datatypes',
                                                                     'This is a required field.' )
                                                           );
            }
            else if( $infoCollectionCheck === true and $isRequired === true )
            {
                $contentObjectAttribute->setValidationError( ezpI18n::tr( 'extension/sckenhancedselection/datatypes',
                                                                     'No POST variable. Please check your configuration.' )
                                                           );
            }
            else
            {
                return eZInputValidator::STATE_ACCEPTED;
            }

            return eZInputValidator::STATE_INVALID;
        }

        return eZInputValidator::STATE_ACCEPTED;
    }
    
    /**
     * Try to returns the most apropriate locale to display now.
     * @param eZContentClassAttribute $classAttribute
     * @return string Locale code (for example "eng-GB")
     * @todo The "guess" mechanism is a little dirty right now : it's pretty hard to get the right context in every case... 
     */
    protected function currentLocale( $classAttribute ) 
    {
        if ( $classAttribute->editLocale() )
        {
            return $classAttribute->editLocale();
        }
        
        $tpl = eZTemplate::instance();
        if ( $tpl->hasVariable( 'language_code' ) )
        {
            return $tpl->variable( 'language_code' );
        }
        
        
        $http = eZHTTPTool::instance();
        if ( $http->hasPostVariable( 'EditLanguage' ) )
        {
            return $http->postVariable( 'EditLanguage' );
        }
        
        
        $ini = eZINI::instance();
        return $ini->variable( 'RegionalSettings', 'ContentObjectLocale' );
    }
    
    protected function initialLocale( $classAttribute )
    {
        $class = eZContentClass::fetch( $classAttribute->attribute( 'contentclass_id' ) );
        $localeObject = eZContentLanguage::fetch( $class->attribute( 'initial_language_id' ) );
        return $localeObject->attribute( 'locale' );
    }
    
    /**
     * Return a simple 'option id' => 'option multilanguage names' associative array from a multilanguage options structure
     * @param array $contentOptionList
     * @return array
     */
    protected static function getMultiLanguageNameById( $contentOptionList ) 
    {
        $return = array();
        foreach( $contentOptionList as $contentOption )
        {
            $return[$contentOption['id']] = $contentOption['name'];
        }
        return $return;
    }
}

eZDataType::register( SckEnhancedSelectionType::DATATYPESTRING, "sckenhancedselectiontype" );
?>