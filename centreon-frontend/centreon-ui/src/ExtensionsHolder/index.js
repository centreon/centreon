import React from "react";
import classnames from 'classnames';
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
  render() {
    const {
      title,
      titleIcon,
      entities,
      onCardClicked,
      onDelete,
      titleColor,
      onInstall,
      onUpdate,
      updating,
      installing,
      type
    } = this.props;
    return (
      <Wrapper>
        <HorizontalLineContent hrTitle={title} />
        <Card>
          <div className={classnames("container__row")}>
            {entities.map(entity => {
              return (
                <div
                  id={`${type}-${entity.id}`}
                  onClick={() => { onCardClicked(entity.id, type)} }
                  className={classnames("container__col-md-3" , "container__col-sm-6" , "container__col-xs-12")}
                >
                  <CardItem
                    itemBorderColor={
                      entity.version.installed
                        ? !entity.version.outdated
                          ? "green"
                          : "orange"
                        : "gray"
                    }
                    {...(entity.licence && entity.licence != "N/A"
                      ? { itemFooterColor: "red" }
                      : {})}
                    {...(entity.licence && entity.licence != "N/A"
                      ? { itemFooterLabel: entity.licence }
                      : {})}
                  >
                    {entity.version.installed ? (
                      <IconInfo iconName="state green" />
                    ) : null}

                    <div className={classnames("custom-title-heading")}>
                      <Title
                        titleColor={titleColor}
                        icon={titleIcon}
                        label={entity.description}
                      />
                      <Subtitle label={`by ${entity.label}`} />
                    </div>
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
                        } else {
                          onCardClicked(id);
                        }
                      }}
                      style={
                        installing[entity.id] || updating[entity.id]
                          ? {
                              opacity: "0.5"
                            }
                          : {}
                      }
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
                      label={`Available ${entity.version.available}`}
                    >
                      {!entity.version.installed ? (
                        <IconContent
                          iconContentColor="white"
                          iconContentType={`${
                            installing[entity.id] ? "update" : "add"
                          }`}
                          loading={installing[entity.id]}
                        />
                      ) : entity.version.outdated ? (
                        <IconContent
                          iconContentColor="white"
                          iconContentType="update"
                          loading={updating[entity.id]}
                        />
                      ) : null}
                    </Button>
                    {entity.version.installed ? (
                      <ButtonAction
                        buttonActionType="delete"
                        buttonIconType="delete"
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
