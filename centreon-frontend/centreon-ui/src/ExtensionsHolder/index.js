import React from "react";
import * as Centreon from '../index';

class ExtensionsHolder extends React.Component {


    render() {
        const { title, entities } = this.props;
        return (
            <Centreon.Wrapper>
                <Centreon.HorizontalLineContent hrTitle={title} />
                <Centreon.Card>
                    <div className="container__row">
                        {
                            entities.map(entity => {
                                return (
                                    <div className="container__col-md-3 container__col-sm-6 container__col-xs-12">
                                        <Centreon.CardItem
                                            itemBorderColor={entity.installed ? (entity.licence && entity.licence != 'N/A' ? "green" : "orange") : "gray"}
                                            {...(entity.licence && entity.licence != 'N/A' ? {itemFooterColor:'red'} : {})}
                                            {...(entity.licence && entity.licence != 'N/A' ? {itemFooterLabel:entity.licence} : {})}
                                            >
                                            <Centreon.IconInfo iconName="state" />
                                            <div className="custom-title-heading">
                                                <Centreon.Title icon="object" label={entity.description} />
                                                <Centreon.Subtitle label={`by ${entity.label}`} />
                                            </div>
                                            <Centreon.Button
                                                buttonType={entity.version.outdated ? "regular" : "bordered"}
                                                color={entity.version.outdated ? "orange" : "blue"}
                                                label={`Available ${entity.version.available}`}
                                                {...(entity.version.outdated ? {iconActionType:"update"} : {})} >
                                                {
                                                    entity.installed === false ? <Centreon.IconContent iconContentType="add" /> : null
                                                }
                                            </Centreon.Button>
                                            {
                                                entity.installed ?
                                                <Centreon.ButtonAction buttonActionType="delete" buttonIconType="delete" /> : null
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
