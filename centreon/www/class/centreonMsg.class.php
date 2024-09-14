<?php

/*
 * Copyright 2005-2021 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
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
    public $color;
    /** @var string */
    public $div;

    /**
     * CentreonMsg constructor
     *
     * @param string|null $divId
     */
    public function __construct($divId = null)
    {
        $this->div = empty($divId) ? "centreonMsg" : $divId;
        $this->color = "#FFFFFF";
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
        $this->setTextStyle("bold");
        $this->setText($message);
        $this->setTimeOut("3");
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
        $this->setTextColor("rgb(255, 102, 102)");
        $this->setTextStyle("bold");
        $this->setText($message);
        $this->setTimeOut("3");
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
        echo "<script type=\"text/javascript\">_setTextStyle(\"$this->div\", \"$style\")</script>";
    }

    /**
     * @param string $color
     *
     * @return void
     */
    public function setTextColor($color): void
    {
        echo "<script type=\"text/javascript\">_setTextColor(\"$this->div\", \"$color\")</script>";
    }

    /**
     * @param string $align
     *
     * @return void
     */
    public function setAlign($align): void
    {
        echo "<script type=\"text/javascript\">_setAlign(\"$this->div\", \"$align\")</script>";
    }

    /**
     * @param string $align
     *
     * @return void
     */
    public function setValign($align): void
    {
        echo "<script type=\"text/javascript\">_setValign(\"$this->div\", \"$align\")</script>";
    }

    /**
     * @param string $color
     *
     * @return void
     */
    public function setBackgroundColor($color): void
    {
        echo "<script type=\"text/javascript\">_setBackgroundColor(\"$this->div\", \"$color\")</script>";
    }

    /**
     * @param string $str
     *
     * @return void
     */
    public function setText($str): void
    {
        echo "<script type=\"text/javascript\">_setText(\"$this->div\", \"$str\")</script>";
    }

    /**
     * @param string $img_url
     *
     * @return void
     */
    public function setImage($img_url): void
    {
        echo "<script type=\"text/javascript\">_setImage(\"$this->div\", \"$img_url\")</script>";
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
        echo "<script type=\"text/javascript\">"
            . "setTimeout(() => { jQuery(\"#" . $this->div . "\").toggle(); }, " . ($sec * 1000) . ");"
            . "</script>";
    }

    /**
     * Clear message box
     *
     * @return void
     */
    public function clear(): void
    {
        echo "<script type=\"text/javascript\">_clear(\"$this->div\")</script>";
    }

    /**
     * @return void
     */
    public function nextLine(): void
    {
        echo "<script type=\"text/javascript\">_nextLine(\"$this->div\")</script>";
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
