<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/app/Http/Controllers',
        __DIR__ . '/routes',
        __DIR__ . '/database',
        __DIR__ . '/tests',
    ])
    ->exclude('storage')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
    '@PSR12' => true,                              // применять набор стандартов PSR-12
    'array_syntax' => ['syntax' => 'short'],       // использовать []
    'no_unused_imports' => true,                   // удалить неиспользуемые use
    'binary_operator_spaces' => ['default' => 'align_single_space'], // выравнивание операторов
    'single_quote' => true,                        // использовать одинарные кавычки где возможно
    'no_superfluous_phpdoc_tags' => true,          // удалять бесполезные phpdoc

    'blank_line_after_namespace' => true,          // пустая строка после namespace
    'blank_line_after_opening_tag' => true,        // пустая строка сразу после <?php
    'no_extra_blank_lines' => true,                // удалять «лишние» пустые строки
    'no_whitespace_in_blank_line' => true,         // убирать пробелы/табы в пустых строках

    'braces' => [                                  // размещение фигурных скобок
        'position_after_functions_and_oop_constructs' => 'next',
        'position_after_control_structures' => 'same'
    ],

    'indentation_type' => true,                    // использовать только пробелы (не табы)
    'line_ending' => true,                         // привести окончания строк к ОС-формату (LF/CRLF)
    'trailing_comma_in_multiline' => [             // запятая в конце многострочных массивов
        'elements' => ['arrays']
    ],

    'phpdoc_align' => ['align' => 'left'],         // выравнивание аннотаций @param/@return в PHPDoc
    'phpdoc_trim' => true,                         // удалять лишние пробелы в PHPDoc
    'phpdoc_no_empty_return' => true,              // убирать `@return void`, если в сигнатуре уже void
    'phpdoc_summary' => true,                      // проверять, чтобы описание в PHPDoc заканчивалось точкой
    ])
    ->setFinder($finder)
    ->setUsingCache(true);
