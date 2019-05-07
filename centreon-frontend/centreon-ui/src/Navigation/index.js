import React, {Component} from 'react';
import classnames from 'classnames';
import styles from './navigation.scss';

class Navigation extends Component {
  render() {
    const {customStyle} = this.props;
    return (
      <ul className={classnames(styles["menu"], styles["menu-items"], styles["list-unstyled"], styles[customStyle ? customStyle : ''])}>
        <li className={classnames(styles["menu-item"], styles["color-lime"])}>
          <span className={classnames(styles["menu-item-link"])}>
            <span className={classnames(styles["iconmoon"], styles["icon-monitoring"])}>
              <span className={classnames(styles["menu-item-name"])}>
                Monitoring
              </span>
            </span>
          </span>

          <ul className={classnames(styles["collapse"], styles["collapsed-items"], styles["list-unstyled"], styles["border-lime"])}>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                Status Details
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["towards-down"], styles["list-unstyled"])}>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Services</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Hosts</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Services Grid</span>
                  </a>
                </li>
              </ul>
            </li>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                Performance
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Main Menu</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Graphs</span>
                  </a>
                </li>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Parameters</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Templates</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Curves</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-lime"])}>
                    <span>Virtual Metrics</span>
                  </a>
                </li>
              </ul>
            </li>
          </ul>
        </li>

        <li className={classnames(styles["menu-item"], styles["active"], styles["color-orange"])}>
          <span className={classnames(styles["menu-item-link"])}>
            <span className={classnames(styles["iconmoon"], styles["icon-reporting"])}>
              <span className={classnames(styles["menu-item-name"])}>
                Reporting
              </span>
            </span>
          </span>

          <ul className={classnames(styles["collapse"], styles["collapsed-items"], styles["list-unstyled"], styles["border-orange"])}>
            <li className={classnames(styles["collapsed-item"], styles["active"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                Status Details
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                    <span>Services</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                    <span>Hosts</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                    <span>Services Grid</span>
                  </a>
                </li>
              </ul>
            </li>
            <li className={classnames(styles["collapsed-item"])}>
              <span className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                Performance
              </span>

              <ul className={classnames(styles["collapse-level"], styles["collapsed-level-items"], styles["first-level"], styles["list-unstyled"])}>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Main Menu</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                    <span>Graphs</span>
                  </a>
                </li>
                <span class={classnames(styles["collapsed-level-title"])}>
                  <span>Parameters</span>
                </span>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                    <span>Templates</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
                    <span>Curves</span>
                  </a>
                </li>
                <li className={classnames(styles["collapsed-level-item"])}>
                  <a href="#" className={classnames(styles["collapsed-item-level-link"], styles["color-orange"])}>
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
