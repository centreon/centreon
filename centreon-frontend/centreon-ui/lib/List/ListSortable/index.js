"use strict";

Object.defineProperty(exports, "__esModule", {
  value: true
});
exports.default = void 0;

var _react = _interopRequireWildcard(require("react"));

var _Checkbox = _interopRequireDefault(require("../../Checkbox"));

var _InputFieldSelect = _interopRequireDefault(require("../../InputField/InputFieldSelect"));

require("./list-sortable.scss");

var _ = require("../..");

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { default: obj }; }

function _interopRequireWildcard(obj) { if (obj && obj.__esModule) { return obj; } else { var newObj = {}; if (obj != null) { for (var key in obj) { if (Object.prototype.hasOwnProperty.call(obj, key)) { var desc = Object.defineProperty && Object.getOwnPropertyDescriptor ? Object.getOwnPropertyDescriptor(obj, key) : {}; if (desc.get || desc.set) { Object.defineProperty(newObj, key, desc); } else { newObj[key] = obj[key]; } } } } newObj.default = obj; return newObj; } }

function _typeof(obj) { if (typeof Symbol === "function" && typeof Symbol.iterator === "symbol") { _typeof = function _typeof(obj) { return typeof obj; }; } else { _typeof = function _typeof(obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }; } return _typeof(obj); }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

function _possibleConstructorReturn(self, call) { if (call && (_typeof(call) === "object" || typeof call === "function")) { return call; } return _assertThisInitialized(self); }

function _assertThisInitialized(self) { if (self === void 0) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return self; }

function _getPrototypeOf(o) { _getPrototypeOf = Object.setPrototypeOf ? Object.getPrototypeOf : function _getPrototypeOf(o) { return o.__proto__ || Object.getPrototypeOf(o); }; return _getPrototypeOf(o); }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function"); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, writable: true, configurable: true } }); if (superClass) _setPrototypeOf(subClass, superClass); }

function _setPrototypeOf(o, p) { _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) { o.__proto__ = p; return o; }; return _setPrototypeOf(o, p); }

var ListSortable =
/*#__PURE__*/
function (_Component) {
  _inherits(ListSortable, _Component);

  function ListSortable() {
    _classCallCheck(this, ListSortable);

    return _possibleConstructorReturn(this, _getPrototypeOf(ListSortable).apply(this, arguments));
  }

  _createClass(ListSortable, [{
    key: "render",
    value: function render() {
      return _react.default.createElement("table", {
        class: "list list-sortable"
      }, _react.default.createElement("thead", null, _react.default.createElement("tr", null, _react.default.createElement("th", {
        scope: "col"
      }, "INDICATORS"), _react.default.createElement("th", {
        scope: "col"
      }, "TYPE"), _react.default.createElement("th", {
        scope: "col"
      }, "DEFINE IMPACT"), _react.default.createElement("th", {
        scope: "col"
      }, "WARNING"), _react.default.createElement("th", {
        scope: "col"
      }, "CRITICAL"), _react.default.createElement("th", {
        scope: "col"
      }, "UNKOWN"))), _react.default.createElement("tbody", null, _react.default.createElement("tr", null, _react.default.createElement("td", null, _react.default.createElement(_Checkbox.default, {
        label: "Lorem Ipsum dolor sit amet",
        name: "all-hosts",
        iconColor: "green"
      })), _react.default.createElement("td", null, "Type 1"), _react.default.createElement("td", null, _react.default.createElement(_.SwitcherMode, null)), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      }))), _react.default.createElement("tr", null, _react.default.createElement("td", null, _react.default.createElement(_Checkbox.default, {
        label: "Lorem Ipsum dolor sit amet",
        name: "all-hosts",
        iconColor: "green"
      })), _react.default.createElement("td", null, "Type 2"), _react.default.createElement("td", null, _react.default.createElement(_.SwitcherMode, null)), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      }))), _react.default.createElement("tr", null, _react.default.createElement("td", null, _react.default.createElement(_Checkbox.default, {
        label: "Lorem Ipsum dolor sit amet",
        name: "all-hosts",
        iconColor: "green"
      })), _react.default.createElement("td", null, "Type 3"), _react.default.createElement("td", null, _react.default.createElement(_.SwitcherMode, null)), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })), _react.default.createElement("td", null, _react.default.createElement(_InputFieldSelect.default, {
        customClass: "small"
      })))));
    }
  }]);

  return ListSortable;
}(_react.Component);

var _default = ListSortable;
exports.default = _default;