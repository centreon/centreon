import React, { Component } from "react";
import Wrapper from "../Wrapper";
import SearchLive from "../Search/SearchLive";
import Switcher from "../Switcher";
import Button from "../Button/ButtonRegular";

class TopFilters extends Component {
  render() {
    const { fullText, switchers, onChange, icon } = this.props;

    return (
      <div className="container container-gray">
        <Wrapper>
          <div className="container__row">
            {fullText ? (
              <div className="container__col-md-3 container__col-xs-12">
                <SearchLive
                  icon={fullText.icon}
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
                                  className="container__col-sm-6 container__col-xs-4 center-vertical mt-1"
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
