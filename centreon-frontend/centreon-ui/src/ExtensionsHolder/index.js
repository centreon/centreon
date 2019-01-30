import React from "react";
import * as Centreon from '../index';

class ExtensionsHolder extends React.Component {


    render() {
        const { title, titleIcon, entities, onCardClicked, onDelete, onInstall, onUpdate, updating, installing } = this.props;
        return (
            <Centreon.Wrapper>
                <Centreon.HorizontalLineContent hrTitle={title} />
                <Centreon.Card>
                    <div className="container__row">
                        {
                            entities.map(entity => {
                                return (
                                    <div onClick={onCardClicked.bind(this, entity.id)} className="container__col-md-3 container__col-sm-6 container__col-xs-12" >
                                        <Centreon.CardItem
                                            itemBorderColor={entity.version.installed ? (!entity.version.outdated ? "green" : "orange") : "gray"}
                                            {...(entity.licence && entity.licence != 'N/A' ? { itemFooterColor: 'red' } : {})}
                                            {...(entity.licence && entity.licence != 'N/A' ? { itemFooterLabel: entity.licence } : {})}
                                        >
                                            {
                                                entity.version.installed ? <Centreon.IconInfo iconName="state" /> : null
                                            }

                                            <div className="custom-title-heading">
                                                <Centreon.Title icon={titleIcon} label={entity.description} />
                                                <Centreon.Subtitle label={`by ${entity.label}`} />
                                            </div>
                                            <Centreon.Button
                                                onClick={(e) => {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    const { id } = entity;
                                                    const { version } = entity;
                                                    if (version.outdated) {
                                                        onUpdate(id)
                                                    } else if (!version.installed) {
                                                        onInstall(id)
                                                    } else {
                                                        onCardClicked(id)
                                                    }
                                                }}
                                                style={
                                                    installing[entity.id] || updating[entity.id] ?
                                                        {
                                                            opacity: '0.5'
                                                        } : {}
                                                }
                                                buttonType={(entity.version.installed ? (entity.version.outdated ? "regular" : "bordered") : "regular")}
                                                color={(entity.version.installed ? (entity.version.outdated ? "orange" : "blue") : "green")}
                                                label={`Available ${entity.version.available}`} >
                                                {
                                                    !entity.version.installed ? <Centreon.IconContent iconContentType={`${installing[entity.id] ? 'update' : 'add'}`} loading={installing[entity.id]} /> :
                                                        ((entity.version.outdated) ? <Centreon.IconContent iconContentType="update" loading={updating[entity.id]} /> : null)
                                                }
                                            </Centreon.Button>
                                            {
                                                entity.version.installed ?
                                                    <Centreon.ButtonAction buttonActionType="delete" buttonIconType="delete"
                                                        onClick={
                                                            (e) => {
                                                                e.preventDefault();
                                                                e.stopPropagation();
                                                                onDelete(entity);
                                                            }
                                                        }
                                                    /> : null
                                            }
                                        </Centreon.CardItem>
                                    </div>
                                )
                            })
                        }
                    </div>
                </Centreon.Card>
            </Centreon.Wrapper>
        )
    }
}

export default ExtensionsHolder;
