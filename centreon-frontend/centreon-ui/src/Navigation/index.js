import React, { Component } from "react";
import classnames from "classnames";
import styles from "./navigation.scss";
import BoundingBox from "../BoundingBox";

class Navigation extends Component {
  state = {
    activeSecondLevel: null,
    navigatedPageId: false,
    hrefOfIframe: false
  };

  componentDidMount() {
    window.addEventListener("react.href.update", this.watchHrefChange, false);
  }

  componentWillUnmount() {
    window.removeEventListener("react.href.update", this.watchHrefChange);
  }

  watchHrefChange = event => {
    if(event.detail.href.match(/p=/)){
      this.setState({
        hrefOfIframe: event.detail.href
      });
    }
  };

  getUrlFromEntry = entryProps => {
    const urlOptions =
      entryProps.page + (entryProps.options !== null ? entryProps.options : "");
    const url = entryProps.is_react
      ? entryProps.url
      : "/main.php" + "?p=" + urlOptions;
    return { url, urlOptions };
  };

  activateSecondLevel = secondLevelPage => {
    const { activeSecondLevel } = this.state;

    this.setState({
      activeSecondLevel:
        activeSecondLevel == secondLevelPage ? true : secondLevelPage
    });
  };

  getActiveTopLevelIndex = pageId => {
    const { navigationData } = this.props;
    let index = -1;
    for (let i = 0; i < navigationData.length; i++) {
      if (
        !isNaN(pageId) &&
        String(pageId).charAt(0) == navigationData[i].page
      ) {
        index = i;
      }
    }
    return index;
  };

  onNavigate = (id, url) => {
    const { onNavigate } = this.props;
    this.setState({
      navigatedPageId: id,
      hrefOfIframe: false
    });
    onNavigate(id, url);
  };

  areSamePage = (page, level, imersion) => {
    return imersion ? (!isNaN(page) && String(page).substring(0,imersion) == level.page) : (!isNaN(page) && page== level.page)
  }

  render() {
    const {
      customStyle,
      navigationData,
      sidebarActive,
      handleDirectClick,
      externalHistory,
      reactRoutes
    } = this.props;
    const { activeSecondLevel, navigatedPageId, hrefOfIframe } = this.state;
    if (!externalHistory) {
      return null;
    }
    const { pathname, search } = externalHistory.location;
    let pageId = "";

    if (navigatedPageId && !hrefOfIframe) {
      pageId = navigatedPageId;
    } else if (hrefOfIframe) {
      if (hrefOfIframe.match(/p=/)) {
        pageId = hrefOfIframe.split("p=")[1]
        if(pageId){
          pageId = pageId.split("&")[0];
        }
      } else {
        pageId = reactRoutes[hrefOfIframe] || hrefOfIframe;
      }
    } else {
      if (search.match(/p=/)) {
        pageId = search.split("p=")[1].split("&")[0];
      } else {
        pageId = reactRoutes[pathname] || pathname;
      }
    }

    const activeIndex = this.getActiveTopLevelIndex(pageId);

    return (
      <ul
        className={classnames(
          styles["menu"],
          styles["menu-items"],
          styles["list-unstyled"],
          styles[customStyle ? customStyle : ""]
        )}
      >
        {navigationData.map((firstLevel, firstLevelIndex) => (
          <li
            className={classnames(styles["menu-item"], {
              [styles[`color-${firstLevel.color}`]]: true,
              [styles[
                firstLevel.toggled ||
                this.areSamePage(pageId,firstLevel,1)
                  ? `active-${firstLevel.color}`
                  : ""
              ]]: true,
              [styles[
                firstLevel.toggled ||
                this.areSamePage(pageId,firstLevel,1)
                  ? `active`
                  : ""
              ]]: true
            })}
            key={`firstLevel-${firstLevel.page}`}
          >
            <span
              className={classnames(styles["menu-item-link"])}
              onDoubleClick={() => {
                this.setState({
                  hrefOfIframe:false
                })
                handleDirectClick(firstLevel.page, firstLevel);
              }}
            >
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
                {
                  [styles[`border-${firstLevel.color}`]]: true,
                  [styles[
                    activeIndex !== -1 &&
                    firstLevelIndex > activeIndex &&
                    sidebarActive &&
                    navigationData[activeIndex].children.length >= 5
                      ? "towards-down"
                      : "towards-up"
                  ]]: true
                }
              )}
            >
              {firstLevel.children.map(secondLevel => {
                const secondLevelUrl = this.getUrlFromEntry(secondLevel);
                return (
                  <li
                    className={classnames(styles["collapsed-item"], {
                      [styles[
                        activeSecondLevel == secondLevel.page ||
                        (!activeSecondLevel &&
                          this.areSamePage(pageId,secondLevel,3))
                          ? `active`
                          : ''
                      ]]: true,
                      [styles[
                        secondLevel.toggled ||
                        this.areSamePage(pageId,secondLevel,3)
                          ? `active-${firstLevel.color}`
                          : ""
                      ]]: true
                    })}
                    onClick={() => {
                      if (secondLevel.groups.length < 1) {
                        this.onNavigate(secondLevel.page, secondLevelUrl);
                      } else if (
                        this.areSamePage(pageId,firstLevel,1)
                      ) {
                        this.activateSecondLevel(secondLevel.page);
                      }
                    }}
                    key={`secondLevel-${secondLevel.page}`}
                  >
                    <span
                      className={classnames(
                        styles["collapsed-item-level-link"],
                        {
                          [styles[`color-${firstLevel.color}`]]: true,
                          [styles[
                            secondLevel.groups.length < 1 ? "img-none" : ""
                          ]]: true
                        }
                      )}
                    >
                      {secondLevel.label}
                    </span>
                    <BoundingBox active={true}>
                      {({ rectBox }) => {
                        let styleFor3rdLevel = {};
                        if (rectBox && rectBox.bottom < 1) {
                          styleFor3rdLevel = {
                            height: rectBox.offsetHeight + rectBox.bottom,
                            overflowY: "scroll"
                          };
                        }
                        return (
                          <ul
                            className={classnames(
                              styles["collapse-level"],
                              styles["collapsed-level-items"],
                              styles["first-level"],
                              styles["list-unstyled"],
                              styles["towards-up"]
                            )}
                            style={styleFor3rdLevel}
                          >
                            {secondLevel.groups.map(group => (
                              <React.Fragment
                                key={`thirdLevelFragment-${group.label}`}
                              >
                                {secondLevel.groups.length > 1 ? (
                                  <span
                                    className={classnames(
                                      styles["collapsed-level-title"]
                                    )}
                                  >
                                    <span>{group.label}</span>
                                  </span>
                                ) : null}
                                {group.children.map(thirdLevel => {
                                  const thirdLevelUrl = this.getUrlFromEntry(
                                    thirdLevel
                                  );
                                  return (
                                    <li
                                      className={classnames(
                                        styles["collapsed-level-item"],
                                        {
                                          [styles[
                                            thirdLevel.toggled ||
                                            this.areSamePage(pageId,thirdLevel)
                                              ? `active`
                                              : ""
                                          ]]: true,
                                          [styles[
                                            thirdLevel.toggled ||
                                            this.areSamePage(pageId,thirdLevel)
                                              ? `active-${firstLevel.color}`
                                              : ""
                                          ]]: true
                                        }
                                      )}
                                      key={`thirdLevel-${thirdLevel.page}`}
                                    >
                                      <a
                                        onClick={() => {
                                          this.onNavigate(
                                            thirdLevel.page,
                                            thirdLevelUrl
                                          );
                                        }}
                                        className={classnames(
                                          styles["collapsed-item-level-link"],
                                          {
                                            [styles[
                                              `color-${firstLevel.color}`
                                            ]]: true
                                          }
                                        )}
                                      >
                                        <span>{thirdLevel.label}</span>
                                      </a>
                                    </li>
                                  );
                                })}
                              </React.Fragment>
                            ))}
                          </ul>
                        );
                      }}
                    </BoundingBox>
                  </li>
                );
              })}
            </ul>
          </li>
        ))}
      </ul>
    );
  }
}

export default Navigation;
