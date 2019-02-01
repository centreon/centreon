import React, { Component } from "react";
import * as Centreon from "../index";

class TopFilters extends Component {
  render() {
    const { fullText, switchers, onChange } = this.props;

    return (
      <div className="container container-gray">
        <Centreon.Wrapper>
          <div className="container__row">
            {fullText ? (
              <div className="container__col-md-3 container__col-xs-12">
                <Centreon.SearchLive
                  onChange={onChange}
                  label={fullText.label}
                  value={fullText.value}
                  filterKey={fullText.filterKey}
                />
              </div>
            ) : null}

            <div className="container__col-md-9 container__col-xs-12">
              <div className="container__row">
                {switchers
                  ? switchers.map((switcherColumn, index) => (
                      <div
                        key={`switcherColumn${index}`}
                        className="container__col-sm-6 container__col-xs-12"
                      >
                        <div
                          key={`switcherSubColumn${index}`}
                          className="container__row"
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
                                <Centreon.Switcher
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
                                  className="container__col-sm-6 container__col-xs-4 center-vertical mt-1"
                                >
                                  <Centreon.Button
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
        </Centreon.Wrapper>
      </div>
    );
  }
}

export default TopFilters;
