import React, { Component } from "react";
import classnames from 'classnames';
import styles from '../global-sass-files/_grid.scss';
import Wrapper from "../Wrapper";
import SearchLive from "../Search/SearchLive";
import Switcher from "../Switcher";
import Button from "../Button/ButtonRegular";

class TopFilters extends Component {
  render() {
    const { fullText, switchers, onChange, icon } = this.props;

    return (
      <div className={classnames(styles["container"], styles["container-gray"])}>
        <Wrapper>
          <div className={classnames(styles["container__row"])}>
            {fullText ? (
              <div className={classnames(styles["container__col-md-3"], styles["container__col-xs-12"])}>
                <SearchLive
                  icon={fullText.icon}
                  onChange={onChange}
                  label={fullText.label}
                  value={fullText.value}
                  filterKey={fullText.filterKey}
                />
              </div>
            ) : null}

            <div className={classnames(styles["container__col-md-9"], styles["container__col-xs-12"])}>
              <div className={classnames(styles["container__row"])}>
                {switchers
                  ? switchers.map((switcherColumn, index) => (
                      <div
                        key={`switcherColumn${index}`}
                        className={classnames(styles["container__col-sm-6"], styles["container__col-xs-12"])}
                      >
                        <div
                          key={`switcherSubColumn${index}`}
                          className={classnames(styles["container__row"])}
                        >
                          {switcherColumn.map(
                            (
                              {
                                customClass,
                                switcherTitle,
                                switcherStatus,
                                button,
                                label,
                                buttonType,
                                color,
                                onClick,
                                filterKey,
                                value
                              },
                              i
                            ) =>
                              !button ? (
                                <Switcher
                                  key={`switcher${index}${i}`}
                                  customClass={customClass}
                                  {...(switcherTitle ? { switcherTitle } : {})}
                                  switcherStatus={switcherStatus}
                                  filterKey={filterKey}
                                  onChange={onChange}
                                  value={value}
                                />
                              ) : (
                                <div
                                  key={`switcher${index}${i}`}
                                  className={classnames(styles["container__col-sm-6"], styles["container__col-xs-4"], styles["center-vertical"], styles["mt-1"])}
                                >
                                  <Button
                                    key={`switcherButton${index}${i}`}
                                    label={label}
                                    buttonType={buttonType}
                                    color={color}
                                    onClick={onClick}
                                  />
                                </div>
                              )
                          )}
                        </div>
                      </div>
                    ))
                  : null}
              </div>
            </div>
          </div>
        </Wrapper>
      </div>
    );
  }
}

export default TopFilters;
