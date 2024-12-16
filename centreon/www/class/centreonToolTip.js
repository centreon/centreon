$.fn.replaceWithPush = function(newElement) {
    var $newElement = $(newElement);

    this.replaceWith($newElement);
    return $newElement;
};

/**
 * @class CentreonToolTip
 * @constructor
 */
function CentreonToolTip()
{
    /**
     * @type {string}
     * @private
     */
	this._className = 'helpTooltip';
    /**
     * @type {string}
     * @private
     */
	this._source = '';
    /**
     * @type {string}
     * @private
     */
	this._title = 'Help';
    /**
     * @type {CentreonToolTip}
     * @private
     */
	var _self = this;

    /**
     * @param {string} name
     * @return string
     */
	this.setClass = function(name) {
		this._className = name;
	}

    /**
     * @param source
     */
	this.setSource = function(source) {
		this._source = source;
	}

    /**
     * @param {string} title
     * @return string
     */
	this.setTitle = function(title) {
		this._title = title;
	}

    /**
     * @return void
     */
	this.render = function() {
		jQuery('img.' + _self._className).each(function(index){
			var el = jQuery(this);
			var newElement = el.replaceWithPush(_self._source);
			newElement.addClass(_self._className);
            newElement.attr('name', el.attr('name'));
            newElement.css('cursor', 'pointer');
            newElement.click(function() {
				TagToTip(
					"help:" + el.attr('name'),
					TITLE, _self._title, CLOSEBTN, true, FIX, [this, 0, 5], BGCOLOR, '#ffff99',
					BORDERCOLOR, 'orange', TITLEFONTCOLOR, 'black', TITLEBGCOLOR, 'orange',
					CLOSEBTNCOLORS, ['','black', 'white', 'red'], WIDTH, -300, SHADOW, true, TEXTALIGN, 'justify'
				);
			});
		});	
	}
}