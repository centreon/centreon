import React, {Component} from 'react';
import classnames from 'classnames';
import styles from './navigation.scss';

class Navigation extends Component {
  render() {
    const {customStyle} = this.props;
    return (
      <ul className={classnames(styles["menu"], styles["menu-items"], styles["list-unstyled"], styles[customStyle ? customStyle : ''])}>

        <li className={classnames(styles["menu-item"], styles["color-2B9E93"])}>
          <span className={classnames(styles["menu-item-link"])}>
            <span className={classnames(styles["iconmoon"], styles["icon-home"])}>
              <span className={classnames(styles["menu-item-name"])}>
                Home
              </span>
            </span>
          </span>

          <ul className={classnames(styles["collapse"], styles["collapsed-items"], styles["list-unstyled"], styles["border-2B9E93"])}>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-2B9E93"])}>
                Custom Views
              </span>
            </li>
          </ul>
        </li>

        <li className={classnames(styles["menu-item"], styles["color-85B446"])}>
          <span className={classnames(styles["menu-item-link"])}>
            <span className={classnames(styles["iconmoon"], styles["icon-monitoring"])}>
              <span className={classnames(styles["menu-item-name"])}>
                Monitoring
              </span>
            </span>
          </span>

          <ul className={classnames(styles["collapse"], styles["collapsed-items"], styles["list-unstyled"], styles["border-85B446"])}>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                Status Details
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Services</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Hosts</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Services Grid</span>
                  </a>
                </li>
              </ul>
            </li>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                Performance
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Main Menu</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Graphs</span>
                  </a>
                </li>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Parameters</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Templates</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Curves</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-85B446"])}>
                    <span>Virtual Metrics</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
        </li>

        <li className={classnames(styles["menu-item"], styles["active"], styles["color-E4932C"])}>
          <span className={classnames(styles["menu-item-link"])}>
            <span className={classnames(styles["iconmoon"], styles["icon-reporting"])}>
              <span className={classnames(styles["menu-item-name"])}>
                Reporting
              </span>
            </span>
          </span>

          <ul className={classnames(styles["collapse"], styles["collapsed-items"], styles["list-unstyled"], styles["border-E4932C"])}>
            <li className={classnames(styles["collapsed-item"], styles["active"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                Status Details
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Services</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Hosts</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Services Grid</span>
                  </a>
                </li>
              </ul>
            </li>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                Performance
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Main Menu</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Graphs</span>
                  </a>
                </li>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Parameters</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Templates</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Curves</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-E4932C"])}>
                    <span>Virtual Metrics</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
        </li>
      </ul>
    );
  }
}

export default Navigation;
