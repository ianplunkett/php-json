<?php 

class JSONParse {

    private $tokens;
    
    private $json;
    
    private $output = array();

    public function __construct($string) {
        $this->tokens = str_split($string);
        $char = $this->next_non_whitespace();
        if($char === '{') {
            $this->json = $this->parse_object();
        } else if ($char === '[') {
            $this->json = $this->parse_array();
        } else {
            throw new Exception("Invalid JSON String");
        }
    }

    public function getJSON(){
        return $this->json;
    }

    private function parse_object() {
        $object_member = $this->parse_key_value_pair();
        while(true) {
            $keys = array_keys($object_member);
            $values = array_values($object_member);
            $object[$keys[0]] = $values[0];
            $char = $this->next_non_whitespace();
            if($char === '}'){
                break;
            } else if ($char !== ',') {
                throw new Exception("Invalid Object! $char");
            }
            $object_member = $this->parse_key_value_pair();
        }
        return (object)$object;
    }

    private function parse_array() {
        $array = array();
        $array_member = $this->parse_value();
        while(true) {
            $array[] = $array_member;
            $char = $this->next_non_whitespace();
            if($char === ']'){
                break;
            } else if ($char !== ',') {
                throw new Exception("Invalid Object! $char");
            }
            $array_member = $this->parse_value();
        }
        return $array;
    }

    private function parse_key_value_pair() {
        $key = $this->parse_string(true, false);
        $char = $this->next_non_whitespace();
        if($char !== ':') {
            throw new Exception("Parse Error!, expected : in key value pair, got $char instead\n".var_dump($this->tokens));
        }

        $value = $this->parse_value($this->tokens);
        return (array($key => $value));
    }

    private function next_non_whitespace() {
        $char = array_shift($this->tokens);
        while(preg_match("/\s/", $char)){
            $char = array_shift($this->tokens);
        }
        return $char;
    }

    private function parse_number() {
        $number_string;
        $has_decimal = false;
        $has_exponential = false;
        $first_char = array_shift($this->tokens);
        if($first_char === '-'){
            $second_char = array_shift($this->tokens);
            if($second_char === '0') {
                $third_char = array_shift($this->tokens);
                if($third_char !== '.') {
                    throw new Exception("Invalid Number - a decimal point must follow a leading zero.");
                }
                $has_decimal = true;
                $number_string .= $first_char.$second_char.$third_char;
            } else if(!preg_match('/\d/', $second_char)) {
                throw new Exception("Invalid Number - a number must follow a negative sign.");
            } else {
                $number_string .= $first_char.$second_char;
            }
        }
        else if($first_char === '0') {
            $second_char = array_shift($this->tokens);
            if($second_char === ',' || $second_char === ']' || $second_char === '}'){
                array_unshift($this->tokens, $second_char);
                return $first_char;
            }
            if($second_char !== '.') {
                throw new Exception("Invalid Number - a decimal point must follow a leading zero");
            }
            $has_decimal = true;
            $number_string .= $first_char.$second_char;
        } else {
            $number_string .= $first_char;
        }
        $char = array_shift($this->tokens);
        while(!preg_match('/\s/', $char)) {
            if($char === '.' && $has_decimal === true) { // Only one decimal allowed
                throw new Exception("Invalid Number - more that one decimal point detected $number_string");
            } // decimal 
            else if($char === '.') {
                $has_decimal = true;
                $number_string .= $char;
            }//Exponential
            else if (($char === 'e' || $char === 'E')  && $has_exponential === true) {
                throw new Exception("Invalid Number - more that one expontential detected");
            } else if ($char === 'e' || $char === 'E') {
                $has_exponential = true;
                $next_char = array_shift($this->tokens);
                if($next_char !== '-' && $next_char !== '+' && !preg_match('/\d/', $next_char)) {
                    throw new Exception("Invalid Number - a plus, minus or digit must follow  an expontential $number_string $next_char");
                }
                $number_string .= $char.$next_char;
            } else if($char === ',' || $char === ']' || $char === '}') { // Short-circuit here.  If we hit a comma the number is done.
                array_unshift($this->tokens, $char);
                return $number_string;
            } else if(!preg_match('/\d/', $char)) {
                throw new Exception("Invalid Number - non-numeric value detected $number_string $char");
            } else {
                $number_string .= $char;
            }
            $char = array_shift($this->tokens);
        }
        return $number_string;
    }

    private function parse_value() {
        $char = $this->next_non_whitespace();
        $value = null;
        if($char === '"') {
            $value = $this->parse_string($false, $false);
        } else if (preg_match("/\d/", $char) || $char === '-') {
            array_unshift($this->tokens, $char);
            $value = $this->parse_number();
        } else if ($char === '{') {
            $next_char = array_shift($this->tokens);
            if($next_char === '}'){
                $value = array();
            } else {
                array_unshift($this->tokens, $next_char);
                $value = $this->parse_object();
            }
        } else if ($char === '[') {
            $next_char = array_shift($this->tokens);
            if($next_char === ']'){
                $value = array();
            } else {
                array_unshift($this->tokens, $next_char);
                $value = $this->parse_array();
            }
        } else if ($char === 't') {
            $true_string = $char.implode('',array_slice($this->tokens, 0, 3));
            $this->tokens = array_slice($this->tokens, 3);
            if($true_string !== 'true'){
                throw new Exception("Parse error");
            }
            $value = true;
        } else if ($char === 'f') {
            $false_string = $char.implode('',array_slice($this->tokens, 0, 4));
            $this->tokens = array_slice($this->tokens, 4);
            if($false_string !== 'false'){
                throw new Exception("Parse error");
            }
            $value = false;
        } else if ($char === 'n') {
            $null_string = $char.implode('', array_slice($this->tokens, 0, 3));
            $this->tokens = array_slice($this->tokens, 3);
            if($null_string !== 'null'){
                throw new Exception("Parse error $null_string");
            }
            $value = null;
        } else {
            throw new Exception("Parse error - bad json string");
        }
        return $value;
    }


    private function parse_string($is_start, $is_end) {
        $specialCharacters = array('"', '\\', '/', 'b', 'f', 'n', 'r', 't', 'u');

        // We have reached the end of a string
        if($is_end === true) {
            return "";
        }
        $char = array_shift($this->tokens);
        // We haven't hit the string yet, just whitespace
        if($is_start === true && (preg_match("/\s/", $char))){  
            return $this->parse_string(true, false);
        } // We hit an invalid character before we've started the string
        else if($is_start === true && $char !== '"') {
            throw new Exception("Invalid String! $char");
        } // We are starting our string
        else if($is_start === true && $char === '"') {
            return $this->parse_string(false, false);
        } // Kinda janky but we need to short-circuit to the first if statement
        else if(!$is_start && $char === '"') {
            return "".$this->parse_string(false, true);
        } // Escape Sequences
        else if($char === '\\') {
            $char = array_shift($this->tokens);
            if(!in_array($char, $specialCharacters)) {
                throw new Exception('String parse error! Invalid escape sequence for char: '. $char. " " . $validEscapeSequence);
            }
            if($char === 'u'){
                $hexArray = array_slice($this->tokens, 0, 4);
                $hexString = implode('', $hexArray);
                $this->tokens = array_slice($this->tokens, 4);
                $results = preg_match("/[0-9a-f]{4}/",$hexString);

                $char =  $this->unichr($hexString);
            }
            return $char.$this->parse_string(false, false);
        }
        else {
            return $char.$this->parse_string(false, false);
        }
        
    }
    // Found this here: http://stackoverflow.com/questions/2934563/how-to-decode-unicode-escape-sequences-like-u00ed-to-proper-utf-8-encoded-cha
    // Not sure if it chokes on anything.
    private function unichr($u) {
        return mb_convert_encoding(pack('H*', $u), 'UTF-8', 'UCS-2BE');
    }

}

