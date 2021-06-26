<?php 
trait DumpsTrait {
    /** 
      *  Write debug to dump files.
      * 
      *  @param  string        $name  The dump filename on the server. 
      *  @param  string|array  $args  Arguments to be dumped.
      * 
      *  @return void
    */
    function _dump(string $name, $args) : void
    {
        if(!defined('DUMP') || !defined('DUMP_DIR')|| !DUMP ) return;
        if(!preg_match('/[a-z0-9-_]+.dump/i', $name)) 
        {
            if(preg_match('/[a-z0-9-_]+/i', $name)) 
                $name .= '.dump';
            else 
                throw new Exception('invalid $name argument.');
        }
        if(is_array($args)) 
        {
            foreach($args as $arg) 
            {
                $this->_dump($name, $arg); // recursivity
            }
        }
        else 
        {
            $sep = str_repeat('-', 80) . "\n";
            $biggerSep = str_repeat('=', 80) . "\n";

            $output  = $sep . '[' . date('Y/m/d h:i:sa', time()) . '] - ' . $_SERVER['REMOTE_ADDR'] . "\n" . $sep 
                        . (is_array($args) || is_object($args) ? wordwrap(printr($args, true), 80) . "\n" : wordwrap($args, 80) . "\n" . $biggerSep . "\n");
        
            file_put_contents((defined('DROP_PATH_PREFIXES') && DROP_PATH_PREFIXES ? str_replace('../', '', DUMP_DIR):DUMP_DIR) . $name, $output, FILE_APPEND);
        }
    }
}
// EOF 