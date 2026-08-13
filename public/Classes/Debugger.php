<?php

namespace Classes;

class Debugger
{

//    public function __construct($variableToDebug)
//    {
//        echo '
//        <style>
//            .debug-output {
//                background: #1e1e1e;
//                color: #dcdcdc;
//                border: 1px solid #444;
//                padding: 16px;
//                margin: 20px 0;
//                font-family: Consolas, monospace;
//                font-size: 14px;
//                line-height: 1.5;
//                white-space: pre-wrap;
//                box-shadow: 0 2px 10px rgba(0,0,0,.2)
//            }
//        </style>';
//
//        echo '<pre class="debug-output">';
//        print_r($variableToDebug);
//        echo '</pre>';
//    }

    public function __construct($variable)
    {
        echo '<pre class="debug-output">';
        echo $this->format($variable);
        echo '</pre>';

        echo '
        <style>
            .debug-output {
                background: #1e1e1e;
                color: #ddd;
                padding: 20px;
                border-radius: 8px;
                font-family: Consolas, Monaco, monospace;
                font-size: 14px;
                line-height: 1.6;
            }

            .debug-key {
                color: #569cd6;
            }

            .debug-string {
                color: #ce9178;
            }

            .debug-number {
                color: #b5cea8;
            }

            .debug-bool {
                color: #c586c0;
            }

            .debug-null {
                color: #808080;
            }
        </style>';
    }


    private function format($value, $indent = 0)
    {
        $space = str_repeat('    ', $indent);

        if (is_array($value)) {
            $output = "[\n";

            foreach ($value as $key => $item) {
                $output .= $space . '    ';
                $output .= '<span class="debug-key">' . htmlspecialchars($key) . '</span>';
                $output .= ' => ';
                $output .= $this->format($item, $indent + 1);
                $output .= "\n";
            }

            $output .= $space . ']';

            return $output;
        }

        if (is_string($value)) {
            return '<span class="debug-string">"' . htmlspecialchars($value) . '"</span>';
        }

        if (is_numeric($value)) {
            return '<span class="debug-number">' . $value . '</span>';
        }

        if (is_bool($value)) {
            return '<span class="debug-bool">' . ($value ? 'true' : 'false') . '</span>';
        }

        if (is_null($value)) {
            return '<span class="debug-null">null</span>';
        }

        return htmlspecialchars(print_r($value, true));
    }
}
