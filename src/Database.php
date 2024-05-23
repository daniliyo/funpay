<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $orig_args = $args;
        $replacements = [];
        
        $access_modif = [
            '?a',
            '?#',
            '?d',
            '?f',
            '?',
        ];

        $access_types = [
            'string',
            'integer',
            'float',
            'boolean',
            'NULL',
    	];
        $access_modif_can_pass_array = [
            '?a',
            '?#',
        ];

        $who_escape_apostrophe = [
            '?#',
        ];

        $who_escape_single_quote = [
            "?a",
            "?",
        ];

        $who_convert_null_to_string = [
            "?a",
            "?#",
            "?"
        ];

        $basicPartPattern = '(\?d)|(\?f)|(\?a)|(\?#)|(\?)';
        $blockPartPattern = '\{.*}';
        $pattern = '/'.$basicPartPattern.'|'.$blockPartPattern.'/i';

        preg_match_all('/\?[^dfa#\{) ]/', $query, $check_valid_all_match);
        if(count($check_valid_all_match[0])){
            throw new Exception('Exists invalid modificator');
        }

        preg_match_all($pattern, $query, $all_match);

        if(count($all_match[0]) != count($args)){
            throw new Exception('The number of modifiers with the passed arguments does not match');
        }

        if(!count($all_match[0]) AND !count($args)){
            return $query;
        }

        foreach($args as $karg=>$arg){

            $arg_types = array(gettype($arg));

            $current_modif = $all_match[0][$karg];

            if(is_array($arg) AND in_array($current_modif, $access_modif_can_pass_array)){
                $arg_types = array_map(function($v){
                    return gettype($v);
                }, $arg);
            }

            if(array_diff($arg_types, $access_types)){
                throw new Exception("An invalid type is passed");
            }


            $curr_arg_to_array = $args[$karg];
            if(!is_array($arg)){
                $curr_arg_to_array = [-1 => $arg];
            }

            array_walk_recursive($curr_arg_to_array, 
                function($v, $k) 
                use($karg, &$args, $current_modif, $who_escape_apostrophe, $who_escape_single_quote)
            {

                if($k < 0){
                    $v = &$args[$karg];
                } else {
                    $v = &$args[$karg][$k];
                } 

                settype($v, gettype($v));
                if(gettype($v) === 'string' AND in_array($current_modif, $who_escape_apostrophe)){
                    $v = "`".$v."`";
                }

                if(gettype($v) === 'string' AND in_array($current_modif, $who_escape_single_quote)){
                    $v = '\''.$v.'\'';
                }

                if(gettype($v) === 'boolean'){
                    $v = (int) $v;
                }

                if(gettype($v) === 'NULL'){
                    $v = 'NULL';
                }
            });

        }

        foreach($all_match[0] as $k=>$v){

            $curr_arg = $args[$k];

            $existBlock = preg_match('/'.$blockPartPattern.'/', $v);

            if($existBlock){
                $block = $v;
                preg_match('/'.$basicPartPattern.'/', $v, $matchInBlock);
                $v = $matchInBlock[0];
            }

            switch ($v) {
            
                case "?d":
                    $curr_arg = $curr_arg ?? (int)$curr_arg ?? 'NULL';
                    break;
                case "?f":
                    $curr_arg = $curr_arg ?? (float)$curr_arg ?? 'NULL';
                    break;
                case "?a":
                    $arrKeys = array_keys($curr_arg);
                    rsort($arrKeys);

                    if(is_string($arrKeys[0])){
                        array_walk($curr_arg, function(&$v, $k){
                            $v = "`".$k."` = ".$v;	
                        });	
                    } 

                    $curr_arg = implode(', ', $curr_arg);
                    break;
                case "?#":
                    if(is_array($curr_arg)){
                        $curr_arg = implode(", ", $curr_arg);
                        break;
                    }
                    break;
                case "?":
                    //
                    break;
            }

            if($existBlock){

                if($orig_args[$k] === $this->skip()){
                    $curr_arg = '';
                    break;
                }
                $curr_arg = preg_replace(
                    [
                        '/'.$basicPartPattern.'/',
                        '/{/',
                        '/}/',
                    ], 
                    [
                        $curr_arg,
                        '',
                        '',
                    ], 
                    $block
                ); 
            }

            $replacements[$k] = $curr_arg;
        }

        $result = preg_replace(array_map(function($v){
        return "/".str_replace("?", "\?", $v)."/i";
        }, $all_match[0]), $replacements, $query, 1);

        return $result;
        }

    public function skip()
    {
        return 1;
    }
}