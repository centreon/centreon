<?php
/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

it("test factory used for HtmlSanitizer", function (): void {
    $string = 'I am a <div><span>king</span></div>';
    $htmlSanitizer = HtmlSanitizer::createFromString($string);
    expect($htmlSanitizer)->toBeInstanceOf(HtmlSanitizer::class)
        ->and($htmlSanitizer->getString())->toBeString()
        ->and($htmlSanitizer->getString())->toEqual($string);
});

it("test sanitize of a string with html by HtmlSanitizer", function (): void {
    $string = 'I am a <div><span>king</span></div>';
    $stringTest = "I am a &lt;div&gt;&lt;span&gt;king&lt;/span&gt;&lt;/div&gt;";
    $htmlSanitizer = HtmlSanitizer::createFromString($string)->sanitize();
    expect($htmlSanitizer->getString())->toBeString()
        ->and($htmlSanitizer->getString())->toEqual($stringTest);
});

it("test remove html tags of a string with html by HtmlSanitizer", function (): void {
    $string = 'I am a <div><span>king</span></div>';
    $stringTest = 'I am a king';
    $htmlSanitizer = HtmlSanitizer::createFromString($string)->removeTags();
    expect($htmlSanitizer->getString())->toBeString()
        ->and($htmlSanitizer->getString())->toEqual($stringTest);
});

it("test remove html tags of a string with html without remove allowed tags by HtmlSanitizer", function (): void {
    $string = 'I am a <div><span>king</span></div>';
    $stringTest = 'I am a <span>king</span>';
    $htmlSanitizer = HtmlSanitizer::createFromString($string)->removeTags(['span']);
    expect($htmlSanitizer->getString())->toBeString()
        ->and($htmlSanitizer->getString())->toEqual($stringTest);
});

it("test remove html tags of a string with html without remove several allowed tags by HtmlSanitizer", function (): void {
    $string = 'I am a <div><span><i>king</i></span></div>';
    $stringTest = 'I am a <span><i>king</i></span>';
    $htmlSanitizer = HtmlSanitizer::createFromString($string)->removeTags(['span', 'i']);
    expect($htmlSanitizer->getString())->toBeString()
        ->and($htmlSanitizer->getString())->toEqual($stringTest);
});
