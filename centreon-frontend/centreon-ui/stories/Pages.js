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
  ButtonAction,
  Tabs,
  Tab,
  SwitcherInputField,
  InputField,
  InputFieldTextarea,
  InputFieldSelect,
  RadioButton,
  HorizontalLineSeparator,
  Checkbox,
  InfoTooltip,
  InputFieldMultiSelect,
  CustomButton,
  SearchLive,
  ListSortable
} from "../src";

// Extensions Page
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


// BAM Corelations Capabilities Page
storiesOf("Pages", module).add("Corelations Capabilities page", () => (
  <React.Fragment>
    <Title titleColor="bam" label="BAM Corelations Capabilities" />
    <br />
    <div className="container container-gray">
      <Tabs>
        <Tab label="Configuration">
          <div className="container__row">
            <div className="container__col-md-2 center-vertical">
              <Subtitle label="Enable business activity" subtitleType="bam" />
            </div>
            <div className="container__col-md-2">
              <SwitcherInputField />
            </div>
          </div>
          <Subtitle label="Information" subtitleType="bam" />
          <div className="container__row">
            <div className="container__col-md-4">
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Name" 
              />
              <InputField 
                error="The field is mandatory" 
                inputSize="middle" 
              />
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Description" 
              />
              <InputFieldTextarea 
                textareaType="middle" 
              />
            </div>
            <div className="container__col-md-4">
              <div className="container__row">
                <div className="container__col-md-6 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Icon" 
                  />
                </div>
                <div className="container__col-md-6">
                  <InputFieldSelect customClass="large" />
                </div>
              </div>
              <br />
              <div className="container__row">
                <div className="container__col-md-7 m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Automatically inherit KPI downtime" 
                  />
                </div>
                <div className="container__col-md-5">
                  <div className="container__row">
                    <div className="container__col-md-4">
                      <RadioButton name="test" iconColor="green" checked={true} label="YES" />
                    </div>
                    <div className="container__col-md-4">
                      <RadioButton name="test" iconColor="green" label="NO" />
                    </div>
                  </div>
                </div>
              </div>
              <div className="container__row">
                <div className="container__col-md-6 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Display on remote server" 
                  />
                </div>
                <div className="container__col-md-6">
                  <InputFieldSelect customClass="large" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-xs-12">
              <HorizontalLineSeparator />
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-xs-12">
              <Subtitle label="Business View" subtitleType="bam" />
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-4">
              <div>
                <InfoTooltip 
                  iconColor="gray" 
                  tooltipText="This is the an example of tooltip" 
                  iconText="Link to Business View(s)" 
                />
              </div>
              <InputFieldSelect customClass="big" />
            </div>
          </div>
          <br />
          <div className="container__row">
            <div className="container__col-xs-12">
              <HorizontalLineSeparator />
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-1 center-vertical">
              <Subtitle label="Notification" subtitleType="bam" />
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-4">
              <div>
                <InfoTooltip 
                  iconColor="gray" 
                  tooltipText="This is the an example of tooltip" 
                  iconText="Contact groups authorized to receive notifications from this Business Activity" 
                />
              </div>
              <InputFieldSelect />
            </div>
            <div className="container__col-md-4">
              <div className="container__row">
                <div className="container__col-md-6 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Notification time period" 
                  />
                </div>
                <div className="container__col-md-6 m-0">
                  <InputFieldSelect customClass="large" />
                </div>
              </div>
              <br />
              <div className="container__row">
                <div className="container__col-md-6 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Notification interval" 
                  />
                </div>
                <div className="container__col-md-6 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="*60 seconds" />
                </div>
              </div>
            </div>
            <div className="container__col-md-4">
              <div className="container__row mb-1">
                <div className="container__col-md-8">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Notification option" 
                  />
                </div>
              </div>
              <div className="container__row">
                <div className="container__col-md-3 m-0">
                  <Checkbox name="test" iconColor="green" checked={true} label="Recovery" />
                </div>
                <div className="container__col-md-3 m-0">
                  <Checkbox name="test" iconColor="green" checked={true} label="Warning" />
                </div>
                <div className="container__col-md-3 m-0">
                  <Checkbox name="test" iconColor="green" checked={true} label="Critical" />
                </div>
                <div className="container__col-md-3 m-0">
                  <Checkbox name="test" iconColor="green" checked={true} label="Flapping" />
                </div>
              </div>
              <div className="container__row">
                <div className="container__col-md-5 m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Enable notification" 
                  />
                </div>
                <div className="container__col-md-5">
                  <div className="container__row">
                    <div className="container__col-md-4">
                      <RadioButton name="test" iconColor="green" checked={true} label="YES" />
                    </div>
                    <div className="container__col-md-4">
                      <RadioButton name="test" iconColor="green" label="NO" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div className="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green normal"
            />
          </div>
        </Tab>
        <Tab label="Indicators">
          <div className="container__row">
            <div className="container__col-md-2 center-vertical m-0">
              <Subtitle label="Select status calculation method" subtitleType="bam" />
            </div>
            <div className="container__col-md-2 m-0">
              <InputFieldSelect customClass="large" />
            </div>
            <div className="container__col-md-1 m-0">
              <CustomButton label="Warning" color="orange" />
            </div>
            <div className="container__col-md-1 p-0 m-0 center-both">
              <IconInfo iconText="Treshold" />
            </div>
            <div className="container__col-md-1 p-0 m-0 center-vertical">
              <InputField 
                type="text"
                inputSize="smallest m-0" 
              />
            </div>
            <div className="container__col-md-1 m-0">
              <CustomButton label="Critical" color="red" />
            </div>
            <div className="container__col-md-1 p-0 m-0 center-both">
              <IconInfo iconText="Treshold" />
            </div>
            <div className="container__col-md-1 p-0 m-0 center-vertical">
              <InputField 
                type="text"
                inputSize="smallest m-0" 
              />
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-12 m-0">
              <Subtitle label="Linked Resources" subtitleType="bam" />
            </div>
          </div>
          <div className="container__row mb-2">
            <div className="container__col-md-3 center-vertical m-0">
              <IconInfo iconText="Type of objects you want to calculate the result on" />
            </div>
            <div className="container__col-md-2 m-0">
              <InputFieldSelect customClass="large" />
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-12 m-0">
              <SearchLive />
            </div>
          </div>
          <div className="container__row mt-1">
            <div className="container__col-md-12 m-0">
              <ListSortable />
            </div>
          </div>
        </Tab>
        <Tab label="Reporting">
          <div className="container__row mt-2">
            <Subtitle label="Reporting" subtitleType="bam" />
          </div>
          <div className="container__row">
            <InfoTooltip 
              iconColor="gray" 
              tooltipText="This is the an example of tooltip" 
              iconText="Extra reporting time periods used in Centreon BI reports" 
            />
          </div>
          <div className="container__row mt-1 mb-2">
            <InputFieldMultiSelect size="medium" />
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA control percentage treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning duration treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </div>
              </div>
            </div>
          </div>
          <div className="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green normal"
            />
          </div>
        </Tab>
        <Tab label="Escalation">
          <div className="container__row mt-2">
            <Subtitle label="Escalations" subtitleType="bam" />
          </div>
          <div className="container__row">
            <InfoTooltip 
              iconColor="gray" 
              tooltipText="This is the an example of tooltip" 
              iconText="Select escalation that applied to this Business Activity" 
            />
          </div>
          <div className="container__row mt-1 mb-2">
            <InputFieldMultiSelect size="medium" />
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA control percentage treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning duration treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-5 p-0">
              <div className="container__row">
                <div className="container__col-md-5 center-vertical m-0">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </div>
                <div className="container__col-md-7 m-0 center-vertical">
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </div>
              </div>
            </div>
          </div>
          <div className="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green normal"
            />
          </div>
        </Tab>
        <Tab label="Event Handler">
          <div className="container__row mt-2">
            <Subtitle label="Event handler configuration" subtitleType="bam" />
          </div>
          <div className="container__row">
            <div className="container__col-md-2 p-0 m-0">
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Enable event handler" 
              />
            </div>
            <div className="container__col-md-2 m-0">
              <div className="container__row">
                <div className="container__col-md-4 m-0">
                  <RadioButton name="test" iconColor="green" checked={true} label="YES" />
                </div>
                <div className="container__col-md-4 m-0">
                  <RadioButton name="test" iconColor="green" label="NO" />
                </div>
              </div>
            </div>
          </div>
          <div className="container__row">
            <div className="container__col-md-2 center-vertical m-0 p-0">
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Event handler command" 
              />
            </div>
            <div className="container__col-md-2 m-0">
              <InputFieldMultiSelect />
            </div>
          </div>
          <div className="container__row mt-1">
            <div className="container__col-md-2 center-vertical m-0 p-0">
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Args" 
              />
            </div>
            <div className="container__col-md-2 m-0">
              <InputField 
                type="text" 
                inputSize="big m-0" 
              />
            </div>
            <div className="container__col-md-1 m-0 center-both">
              <Button
                buttonType="validate"
                color="green normal icon"
                iconActionType="arrow-left"
                iconColor="white"
              />
            </div>
            <div className="container__col-md-2 m-0">
              <InputField 
                type="text" 
                inputSize="big m-0" 
              />
            </div>
          </div>
          <div className="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green normal"
            />
          </div>
        </Tab>
      </Tabs>
    </div>
  </React.Fragment>
), {notes: "A very simple component"});