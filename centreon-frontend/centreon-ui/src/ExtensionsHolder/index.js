import React from "react";
import cardStyles from "../Card/card.scss";
import Wrapper from "../Wrapper";
import HorizontalLineContent from "../HorizontalLines/HorizontalLineContent";
import Card from "../Card";
import CardItem from "../Card/CardItem";
import IconInfo from "../Icon/IconInfo";
import Title from "../Title";
import Subtitle from "../Subtitle";
import Button from "../Button/ButtonRegular";
import IconContent from "../Icon/IconContent";
import ButtonAction from "../Button/ButtonAction";

class ExtensionsHolder extends React.Component {

  // remove "centreon" word from the begin of the module/widget description
  parseDescription = description => {
    return description.replace(/^centreon\s+(\w+)/i, (_, $1) => $1);
  }

  // get CardItem props to display license information in footer
  getPropsFromLicense = licenseInfo => {
    let licenseProps = {};

    if (licenseInfo && licenseInfo.required) {
      if (!licenseInfo.expiration_date) {
        licenseProps = {
          itemFooterColor: "red",
          itemFooterLabel: "License is not valid",
        };
      } else if (!isNaN(Date.parse(licenseInfo.expiration_date))) {
        // @todo move this logic to centreon. Furthermore, it will facilitate translation
        // @todo use moment to convert date in the proper format (locale and timezone from user)
        const expirationDate = new Date(licenseInfo.expiration_date);
        licenseProps = {
          itemFooterColor: "green",
          itemFooterLabel: `License is valid until : ${expirationDate.toISOString().slice(0,10)}`,
        };
      } else {
        licenseProps = {
          itemFooterColor: "red",
          itemFooterLabel: "Cannot get license expiration date",
        };
      }
    }

    return licenseProps;
  }

  render() {
    const {
      title,
      entities,
      onCardClicked,
      onDelete,
      titleColor,
      hrColor,
      hrTitleColor,
      onInstall,
      onUpdate,
      updating,
      installing,
      type
    } = this.props;

    return (
      <Wrapper>
        <HorizontalLineContent hrColor={hrColor} hrTitleColor={hrTitleColor} hrTitle={title}/>
        <Card>
          <div>
            {entities.map(entity => {
              return (
                <div
                  id={`${type}-${entity.id}`}
                  onClick={() => { onCardClicked(entity.id, type)} }
                  className={cardStyles["card-inline"]}
                >
                  <CardItem
                    itemBorderColor={
                      entity.version.installed
                        ? !entity.version.outdated
                          ? "green"
                          : "orange"
                        : "gray"
                    }
                    {...this.getPropsFromLicense(entity.license)}
                  >
                    {entity.version.installed ? (
                      <IconInfo iconPosition="info-icon-position" iconName="state" iconColor="green" />
                    ) : null}

                    <Title
                      labelStyle={{fontSize: "16px"}}
                      titleColor={titleColor}
                      label={this.parseDescription(entity.description)}
                      title={entity.description}
                    >
                      <Subtitle label={`by ${entity.label}`} />
                    </Title>
                    <Button
                      onClick={e => {
                        e.preventDefault();
                        e.stopPropagation();
                        const { id } = entity;
                        const { version } = entity;
                        if (version.outdated && !updating[entity.id]) {
                          onUpdate(id, type);
                        } else if (!version.installed && !installing[entity.id]) {
                          onInstall(id, type);
                        }
                      }}
                      customClass="button-card-position"
                      style={{
                        opacity: (installing[entity.id] || updating[entity.id]) ? "0.5" : "inherit",
                        cursor: entity.version.installed ? "default" : "pointer"
                      }}
                      buttonType={
                        entity.version.installed
                          ? entity.version.outdated
                            ? "regular"
                            : "bordered"
                          : "regular"
                      }
                      color={
                        entity.version.installed
                          ? entity.version.outdated
                            ? "orange"
                            : "blue"
                          : "green"
                      }
                      label={(!entity.version.installed ? 'Available ' : '') + entity.version.available}
                    >
                      {!entity.version.installed ? (
                        <IconContent
                          iconContentColor="white"
                          iconContentType={`${
                            installing[entity.id] ? "update" : "add"
                          }`}
                          loading={installing[entity.id]}
                          customClass="content-icon-button"
                        />
                      ) : entity.version.outdated ? (
                        <IconContent
                          iconContentColor="white"
                          iconContentType="update"
                          loading={updating[entity.id]}
                          customClass="content-icon-button"
                        />
                      ) : null}
                    </Button>
                    {entity.version.installed ? (
                      <ButtonAction
                        buttonActionType="delete"
                        buttonIconType="delete"
                        customPosition="button-action-card-position"
                        iconColor="gray"
                        onClick={e => {
                          e.preventDefault();
                          e.stopPropagation();

                          onDelete(entity, type);
                        }}
                      />
                    ) : null}
                  </CardItem>
                </div>
              );
            })}
          </div>
        </Card>
      </Wrapper>
    );
  }
}

export default ExtensionsHolder;
