<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

/**
 * Class
 *
 * @class CentreonMsg
 * @description Class that displays any kind of information between the html header containing logo
 *              and the horizontal menu
 */
class CentreonMsg
{
    /** @var string */
    public $color = '#FFFFFF';

    /** @var string */
    public $div;

    /**
     * CentreonMsg constructor
     *
     * @param string|null $divId
     */
    public function __construct($divId = null)
    {
        $this->div = empty($divId) ? 'centreonMsg' : $divId;
    }

    /**
     * Display an information message.
     *
     * @param string $message
     *
     * @return void
     */
    public function info(string $message): void
    {
        $this->setTextStyle('bold');
        $this->setText($message);
        $this->setTimeOut('3');
    }

    /**
     * Display an error message.
     *
     * @param string $message
     *
     * @return void
     */
    public function error(string $message): void
    {
        $this->setTextColor('rgb(255, 102, 102)');
        $this->setTextStyle('bold');
        $this->setText($message);
        $this->setTimeOut('3');
    }

    /**
     * Sets style of text inside Div
     *
     * @param string $style
     *
     * @return void
     */
    public function setTextStyle($style): void
    {
        echo "<script type=\"text/javascript\">_setTextStyle(\"{$this->div}\", \"{$style}\")</script>";
    }

    /**
     * @param string $color
     *
     * @return void
     */
    public function setTextColor($color): void
    {
        echo "<script type=\"text/javascript\">_setTextColor(\"{$this->div}\", \"{$color}\")</script>";
    }

    /**
     * @param string $align
     *
     * @return void
     */
    public function setAlign($align): void
    {
        echo "<script type=\"text/javascript\">_setAlign(\"{$this->div}\", \"{$align}\")</script>";
    }

    /**
     * @param string $align
     *
     * @return void
     */
    public function setValign($align): void
    {
        echo "<script type=\"text/javascript\">_setValign(\"{$this->div}\", \"{$align}\")</script>";
    }

    /**
     * @param string $color
     *
     * @return void
     */
    public function setBackgroundColor($color): void
    {
        echo "<script type=\"text/javascript\">_setBackgroundColor(\"{$this->div}\", \"{$color}\")</script>";
    }

    /**
     * @param string $str
     *
     * @return void
     */
    public function setText($str): void
    {
        echo "<script type=\"text/javascript\">_setText(\"{$this->div}\", \"{$str}\")</script>";
    }

    /**
     * @param string $img_url
     *
     * @return void
     */
    public function setImage($img_url): void
    {
        echo "<script type=\"text/javascript\">_setImage(\"{$this->div}\", \"{$img_url}\")</script>";
    }

    /**
     * If you want to display your message for a limited time period, just call this function
     *
     * @param int $sec
     *
     * @return void
     */
    public function setTimeOut($sec): void
    {
        echo '<script type="text/javascript">'
            . 'setTimeout(() => { jQuery("#' . $this->div . '").toggle(); }, ' . ($sec * 1000) . ');'
            . '</script>';
    }

    /**
     * Clear message box
     *
     * @return void
     */
    public function clear(): void
    {
        echo "<script type=\"text/javascript\">_clear(\"{$this->div}\")</script>";
    }

    /**
     * @return void
     */
    public function nextLine(): void
    {
        echo "<script type=\"text/javascript\">_nextLine(\"{$this->div}\")</script>";
    }
}

?>
<script type="text/javascript">
    var __image_lock = 0;

    function _setBackgroundColor(div_str, color) {
        document.getElementById(div_str).style.backgroundColor = color;
    }

    function _setText(div_str, str) {
        var my_text = document.createTextNode(str);
        var my_div = document.getElementById(div_str);

        my_div.appendChild(my_text);
    }

    function _setImage(div_str, url) {
        var _image = document.createElement("img");
        _image.src = url;
        _image.id = "centreonMsg_img";
        var my_div = document.getElementById(div_str);
        my_div.appendChild(_image);
    }

    function _clear(div_str) {
        document.getElementById(div_str).innerHTML = "";
    }

    function _setAlign(div_str, align) {
        var my_div = document.getElementById(div_str);

        my_div.style.textAlign = align;
    }

    function _setValign(div_str, align) {
        var my_div = document.getElementById(div_str);

        my_div.style.verticalAlign = align;
    }

    function _setTextStyle(div_str, style) {
        var my_div = document.getElementById(div_str);

        my_div.style.fontWeight = style;
    }

    function _setTextColor(div_str, color) {
        var my_div = document.getElementById(div_str);

        my_div.style.color = color;
    }

    function _nextLine(div_str) {
        var my_br = document.createElement("br");
        var my_div = document.getElementById(div_str);
        my_div.appendChild(my_br);
    }

    function _setTimeout(div_str, sec) {
        sec *= 1000;
        setTimeout(function () {
            jQuery(`#${div_str}`).toggle()
        }, sec)
    }
</script>
