import React, { Component } from "react";
import classnames from "classnames";
import styles from "./navigation.scss";

class Navigation extends Component {
  render() {
    const { customStyle, navigationData } = this.props;
    return (
      <ul
        className={classnames(
          styles["menu"],
          styles["menu-items"],
          styles["list-unstyled"],
          styles[customStyle ? customStyle : ""]
        )}
      >
        {navigationData.map(firstLevel => (
          <li
            className={classnames(styles["menu-item"], {
              [styles[`color-${firstLevel.color}`]]: true
            })}
          >
            <span className={classnames(styles["menu-item-link"])}>
              <span
                className={classnames(styles["iconmoon"], {
                  [styles[`icon-${firstLevel.icon}`]]: true
                })}
              >
                <span className={classnames(styles["menu-item-name"])}>
                  {firstLevel.label}
                </span>
              </span>
            </span>
            <ul
              className={classnames(
                styles["collapse"],
                styles["collapsed-items"],
                styles["list-unstyled"],
                { [styles[`border-${firstLevel.color}`]]: true }
              )}
            >
              {firstLevel.children.map(secondLevel => (
                <li className={classnames(styles["collapsed-item"])}>
                  <span
                    className={classnames(styles["collapsed-item-level-link"], {
                      [styles[`color-${firstLevel.color}`]]: true
                    })}
                  >
                    {secondLevel.label}
                  </span>

                  <ul
                    className={classnames(
                      styles["collapse-level"],
                      styles["collapsed-level-items"],
                      styles["first-level"],
                      styles["list-unstyled"]
                    )}
                  >
                    {secondLevel.groups.map(group => (
                      <React.Fragment>
                        {secondLevel.groups.length > 1 ? (
                          <span
                            class={classnames(styles["collapsed-level-title"])}
                          >
                            <span>{group.label}</span>
                          </span>
                        ) : null}
                        {group.children.map(thirdLevel => (
                          <li
                            className={classnames(
                              styles["collapsed-level-item"]
                            )}
                          >
                            <a
                              href="#"
                              className={classnames(
                                styles["collapsed-item-level-link"],
                                { [styles[`color-${firstLevel.color}`]]: true }
                              )}
                            >
                              <span>{thirdLevel.label}</span>
                            </a>
                          </li>
                        ))}
                      </React.Fragment>
                    ))}
                  </ul>
                </li>
              ))}
            </ul>
          </li>
        ))}
      </ul>
    );
  }
}

export default Navigation;
