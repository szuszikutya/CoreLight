<?php

    /*
     * Custom Boot sequence example
     */

class boot extends Core_Boot
{
    function initSession ()
    {
        // You can here set visitors session, or everything else.
    }

    function initView ()
    {
        // Or manually overwrite|edit the "final build" method
        parent::initView ();
        echo "\n<!-- Final build finished...-->\n";
    }

}