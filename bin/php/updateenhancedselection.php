#!/usr/bin/env php
<?php

require "autoload.php";

$cli = eZCLI::instance();

$script = eZScript::instance( array( 'description' => 'Update the datatype ezenhancedselection to the new version',
                                      'use-session' => false,
                                      'use-modules' => false,
                                      'use-extensions' => true ) );

$script->startup();
$script->initialize();

$cli->output( 'Updating ezenhancedselection to sckenhancedselection...' );
$cli->output();

// Find all class attributes based on ezenhancedselection
$db = eZDB::instance();

$IDs = $db->arrayQuery( "SELECT id
                         FROM ezcontentclass_attribute
                         WHERE version = 0
                         AND data_type_string = 'ezenhancedselection'" );
                         
$cli->output( $cli->stylize( 'bold', 'Updating class attributes' ) );
if( is_array( $IDs ) and count( $IDs ) > 0 )
{
    foreach( $IDs as $id )
    {
        $cli->output( 'Updating class attribute: id - ' . $id['id'] );
        $classAttrib = eZContentClassAttribute::fetch( $id['id'] );
        $content = $classAttrib->content();

        $classAttrib->setAttribute( 'data_type_string' );
        $classAttrib->DataTypeString = 'sckenhancedselection';
        $classAttrib->setContent( $content );
        $classAttrib->store();
        
        $classAttrib->setAttribute( 'data_int1', 0 );
        $classAttrib->setAttribute( 'data_text1', '' );
        $classAttrib->store();
        
        unset( $classAttrib );
    }
}
else
{
    $cli->output( 'No class attributes to update!' );
}

$IDs = $db->arrayQuery( "SELECT id, version
                         FROM ezcontentobject_attribute
                         WHERE data_type_string = 'ezenhancedselection'" );

$cli->output();
$cli->output( $cli->stylize( 'bold', 'Updating object attributes' ) );
if( is_array( $IDs ) and count( $IDs ) > 0 )
{
    foreach( $IDs as $id )
    {
        $cli->output( 'Updating object attribute: id - ' . $id['id'] . ' & version - ' . $id['version'] );
        $objectAttrib = eZContentObjectAttribute::fetch( $id['id'], $id['version'] );
        
        $textString = $objectAttrib->attribute( 'data_text' );
        $textArray = explode( '***', $textString );
        
        $objectAttrib->setAttribute( 'data_type_string', 'sckenhancedselection' );
        $objectAttrib->DataTypeString = 'sckenhancedselection';
        $objectAttrib->setAttribute( 'data_text', serialize( $textArray ) );
        $objectAttrib->store();
        
        $objectAttrib->updateSortKey();
        
        $object = $objectAttrib->attribute( 'object' );
        $class = $object->attribute( 'content_class' );
        
        // Reset the name
        $object->setName( $class->contentObjectName( $object ) );
        
        // Update the nodes
        $nodes = $object->attribute( 'assigned_nodes' );
        
        foreach( $nodes as $node )
        {
            eZContentOperationCollection::publishNode( $node->attribute( 'parent_node_id' ),
												       $object->attribute( 'id' ),
												       $object->attribute( 'current_version' ),
												       $object->attribute( 'main_node_id' ) );
        }
        
        eZSearch::removeObject( $object );
        eZSearch::addObject( $object );
        
        unset( $objectAttrib, $object, $class, $node );
    }
}
else
{
    $cli->output( 'No object attributes to update!' );
}

$cli->output();
$cli->output( 'Done.' );

$script->shutdown();

?>
