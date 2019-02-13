import React from "react";
import {storiesOf} from "@storybook/react";
import {
  Wrapper,
  TopFilters,
  Button,
  HorizontalLineContent,
  Card,
  CardItem,
  IconInfo,
  Title,
  Subtitle,
  ButtonAction
} from "../src";

storiesOf("Pages", module).add("Extensions page", () => (
  <React.Fragment>
    <div className="container container-gray">
      <TopFilters
        fullText={{
        label: "Search:",
        onChange: a => {
          console.log(a);
        }
      }}
        switchers={[
        [
          {
            customClass: "container__col-md-4 container__col-xs-4",
            switcherTitle: "Status:",
            switcherStatus: "Not installed",
            defaultValue: false,
            onChange: value => {
              console.log(value);
            }
          }, {
            customClass: "container__col-md-4 container__col-xs-4",
            switcherStatus: "Installed",
            defaultValue: false,
            onChange: value => {
              console.log(value);
            }
          }, {
            customClass: "container__col-md-4 container__col-xs-4",
            switcherStatus: "Update",
            defaultValue: false,
            onChange: value => {
              console.log(value);
            }
          }
        ],
        [
          {
            customClass: "container__col-sm-3 container__col-xs-4",
            switcherTitle: "Type:",
            switcherStatus: "Module",
            defaultValue: false,
            onChange: value => {
              console.log(value);
            }
          }, {
            customClass: "container__col-sm-3 container__col-xs-4",
            switcherStatus: "Update",
            defaultValue: false,
            onChange: value => {
              console.log(value);
            }
          }, {
            button: true,
            label: "Clear Filters",
            color: "black",
            buttonType: "bordered",
            onClick: () => {
              console.log("Clear filters clicked");
            }
          }
        ]
      ]}/>
    </div>
    <Wrapper>
      <Button
        label={"update all"}
        buttonType="regular"
        color="orange"
        customClass="mr-2"/>
      <Button
        label={"install all"}
        buttonType="regular"
        color="green"
        customClass="mr-2"/>
      <Button label={"upload license"} buttonType="regular" color="blue"/>
    </Wrapper>
    <Wrapper>
      <HorizontalLineContent hrTitle="Modules"/>
      <Card>
        <div className="container__row">
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="orange"
              itemFooterColor="red"
              itemFooterLabel="Licence expire at 12/08/2019"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state green"/>
              <div className="custom-title-heading">
                <Title
                  icon="object"
                  label="Engine-status"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="regular"
                color="orange"
                label="Available 3.1.5"
                iconActionType="update"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </div>
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="green"
              itemFooterColor="orange"
              itemFooterLabel="Licence expire at 12/08/2019"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state green"/>
              <div className="custom-title-heading">
                <Title
                  icon="object"
                  label="Engine-status"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="bordered"
                color="blue"
                label="Available 2.3.5"
                iconActionType="update"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
              <ButtonAction
                iconColor='gray'
                buttonActionType="delete"
                buttonIconType="delete"
                onClick={() => {
                alert("Button delete clicked");
              }}/>
            </CardItem>
          </div>
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <div className="custom-title-heading">
                <Title
                  icon="object"
                  label="Engine-status"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </div>
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <div className="custom-title-heading">
                <Title
                  icon="object"
                  label="Engine-status"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </div>
        </div>
      </Card>
    </Wrapper>
    <Wrapper>
      <HorizontalLineContent hrTitle="Widgets"/>
      <Card>
        <div className="container__row">
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="orange"
              itemFooterColor="blue"
              itemFooterLabel="Licence 5 hosts"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state green"/>
              <div className="custom-title-heading">
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="regular"
                color="orange"
                label="Available 3.1.5"
                iconActionType="update"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
              <ButtonAction
                iconColor='gray'
                buttonActionType="delete"
                buttonIconType="delete"
                onClick={() => {
                alert("Button delete clicked");
              }}/>
            </CardItem>
          </div>
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="green"
              itemFooterColor="red"
              itemFooterLabel="Licence expire at 12/08/2019"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state green"/>
              <div className="custom-title-heading">
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="bordered"
                color="blue"
                label="Available 3.5.6"
                iconActionType="update"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
              <ButtonAction
                iconColor='gray'
                buttonActionType="delete"
                buttonIconType="delete"
                onClick={() => {
                alert("Button delete clicked");
              }}/>
            </CardItem>

          </div>
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <div className="custom-title-heading">
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </div>
          <div className="container__col-md-3 container__col-xs-12">
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <div className="custom-title-heading">
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </div>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </div>
        </div>
      </Card>
    </Wrapper>
  </React.Fragment>
), {notes: "A very simple component"});
