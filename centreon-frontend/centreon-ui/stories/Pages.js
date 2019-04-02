import React from "react";
import classnames from 'classnames';
import styles from '../src/global-sass-files/_grid.scss';
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
  RadioButton,
  HorizontalLineSeparator,
  Checkbox,
  InfoTooltip,
  InputFieldMultiSelect,
  CustomButton,
  SearchLive,
  ListSortable,
  CustomRow,
  CustomColumn,
  CustomStyles
} from "../src";

// Extensions Page
storiesOf("Pages", module).add("Extensions page", () => (
  <React.Fragment>
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
          customClass: classnames(styles["container__col-md-3"] , styles["container__col-xs-4"]),
          switcherTitle: "Status:",
          switcherStatus: "Not installed",
          defaultValue: false,
          onChange: value => {
            console.log(value);
          }
        }, {
          customClass: classnames(styles["container__col-md-3"] , styles["container__col-xs-4"]),
          switcherStatus: "Installed",
          defaultValue: false,
          onChange: value => {
            console.log(value);
          }
        }, {
          customClass: classnames(styles["container__col-md-3"] , styles["container__col-xs-4"]),
          switcherStatus: "Update",
          defaultValue: false,
          onChange: value => {
            console.log(value);
          }
        }
      ],
      [
        {
          customClass: classnames(styles["container__col-sm-3"] , styles["container__col-xs-4"]),
          switcherTitle: "Type:",
          switcherStatus: "Module",
          defaultValue: false,
          onChange: value => {
            console.log(value);
          }
        }, {
          customClass: classnames(styles["container__col-sm-3"] , styles["container__col-xs-4"]),
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
        <CustomRow>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
          <CardItem
              itemBorderColor="orange"
              itemFooterColor="red"
              itemFooterLabel="Licence expire at 12/08/2019"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state" iconColor="green" iconPosition="info-icon-position" />
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="object"
                  label="Engine-status"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="regular"
                color="orange"
                label="Available 3.1.5"
                iconActionType="update"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </CustomColumn>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="green"
              itemFooterColor="orange"
              itemFooterLabel="Licence expire at 12/08/2019"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state" iconColor="green" iconPosition="info-icon-position" />
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="object"
                  label="Engine-status"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="bordered"
                color="blue"
                label="Available 2.3.5"
                iconActionType="update"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
              <ButtonAction
                iconColor='gray'
                buttonActionType="delete"
                buttonIconType="delete"
                customPosition="button-action-card-position"
                onClick={() => {
                alert("Button delete clicked");
              }}/>
            </CardItem>
          </CustomColumn>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="object"
                  label="Engine-status"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </CustomColumn>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="object"
                  label="Engine-status"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </CustomColumn>
        </CustomRow>

      </Card>
    </Wrapper>
    <Wrapper>
      <HorizontalLineContent hrTitle="Widgets"/>
      <Card>
        <CustomRow>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="orange"
              itemFooterColor="blue"
              itemFooterLabel="Licence 5 hosts"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state green"/>
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="regular"
                color="orange"
                label="Available 3.1.5"
                iconActionType="update"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
              <ButtonAction
                iconColor='gray'
                buttonActionType="delete"
                buttonIconType="delete"
                customPosition="button-action-card-position"
                onClick={() => {
                alert("Button delete clicked");
              }}/>
            </CardItem>
          </CustomColumn>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="green"
              itemFooterColor="red"
              itemFooterLabel="Licence expire at 12/08/2019"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <IconInfo iconName="state green"/>
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="bordered"
                color="blue"
                label="Available 3.5.6"
                iconActionType="update"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
              <ButtonAction
                iconColor='gray'
                buttonActionType="delete"
                buttonIconType="delete"
                customPosition="button-action-card-position"
                onClick={() => {
                alert("Button delete clicked");
              }}/>
            </CardItem>

          </CustomColumn>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </CustomColumn>
          <CustomColumn customColumn="md-3" additionalStyles={["display-flex", "container__col-xs-12"]}>
            <CardItem
              itemBorderColor="gray"
              onClick={() => {
              alert("Card clicked- open popin");
            }}>
              <CustomStyles customStyles={["custom-title-heading"]}>
                <Title
                  icon="puzzle"
                  label="Plugin pack manager"
                  titleColor="blue"
                  customTitleStyles="custom-title-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
                <Subtitle
                  label="by Centreon"
                  customSubtitleStyles="custom-subtitle-styles"
                  onClick={() => {
                  alert("Card clicked- open popin");
                }}/>
              </CustomStyles>
              <Button
                buttonType="regular"
                color="green"
                label="Available 3.1.5"
                iconActionType="add"
                iconColor='white'
                position='button-card-position'
                onClick={() => {
                alert("Button clicked");
              }}/>
            </CardItem>
          </CustomColumn>
        </CustomRow>
      </Card>
    </Wrapper>
  </React.Fragment>
), {notes: "A very simple component"});


// BAM Corelations Capabilities Page
storiesOf("Pages", module).add("Corelations Capabilities page", () => (
  <React.Fragment>
    <Title titleColor="bam" label="BAM Corelations Capabilities" />
    <br />
    
    <CustomStyles additionalStyles={["container", "container-gray", "p-0"]}>
      <Tabs>
        <Tab label="Configuration">
          <CustomRow>
            <CustomColumn customColumn="md-2" additionalStyles={["center-vertical"]}>
              <Subtitle label="Enable business activity" subtitleType="bam" />
            </CustomColumn>
            <CustomColumn customColumn="md-2">
              <SwitcherInputField />
            </CustomColumn>
          </CustomRow>
          <Subtitle label="Information" subtitleType="bam" />

          <CustomRow>
            <CustomColumn customColumn="md-4">
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
            </CustomColumn>

            <CustomColumn customColumn="md-4">

              <CustomRow>
                <CustomColumn customColumn="md-6" additionalStyles={["center-vertical", "m-0"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Icon" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-6">
                  <InputFieldMultiSelect customClass="medium" />
                </CustomColumn>
              </CustomRow>

              <br />
              <CustomRow>

                <CustomColumn customColumn="md-7" additionalStyles={["m-0"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Automatically inherit KPI downtime" 
                  />
                </CustomColumn>

                <CustomColumn customColumn="md-5">
                  <CustomRow>
                    <CustomColumn customColumn="md-4">
                      <RadioButton name="test" iconColor="green" checked={true} label="YES" />
                    </CustomColumn>
                    <CustomColumn customColumn="md-4">
                      <RadioButton name="test" iconColor="green" label="NO" />
                    </CustomColumn>
                  </CustomRow>
                </CustomColumn>

              </CustomRow>

              <CustomRow>
                <CustomColumn customColumn="md-6" additionalStyles={["center-vertical", "m-0"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Display on remote server" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-6">
                  <InputFieldMultiSelect customClass="medium" />
                </CustomColumn>
              </CustomRow>
            </CustomColumn>

          </CustomRow>
          
          <CustomRow additionalStyles={["mt-2"]}>
            <CustomColumn customColumn="xs-12">
              <HorizontalLineSeparator />
            </CustomColumn>
          </CustomRow>
          <CustomRow>
            <CustomColumn customColumn="xs-12">
              <Subtitle label="Business View" subtitleType="bam" />
            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-4">
              <div>
                <InfoTooltip 
                  iconColor="gray" 
                  tooltipText="This is the an example of tooltip" 
                  iconText="Link to Business View(s)" 
                />
              </div>
              <InputFieldMultiSelect size="medium" />
            </CustomColumn>
          </CustomRow>

          <br />
          <CustomRow>
            <CustomColumn customColumn="xs-12">
              <HorizontalLineSeparator />
            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-1" additionalStyles={["center-vertical"]}>
              <Subtitle label="Notification" subtitleType="bam" />
            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-4">
              <div>
                <InfoTooltip 
                  iconColor="gray" 
                  tooltipText="This is the an example of tooltip" 
                  iconText="Contact groups authorized to receive notifications from this Business Activity" 
                />
              </div>
              <InputFieldMultiSelect size="medium" />
            </CustomColumn>

            <CustomColumn customColumn="md-4">

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["center-vertical", "m-0"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Notification time period" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0"]}>
                  <InputFieldMultiSelect size="big" />
                </CustomColumn>
              </CustomRow>

              <br />
              <CustomRow>

                <CustomColumn customColumn="md-5" additionalStyles={["center-vertical", "m-0"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Notification interval" 
                  />
                </CustomColumn>

                <CustomColumn customColumn="md-7" additionalStyles={["center-baseline", "m-0"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest" 
                  />
                  <IconInfo iconText="*60 seconds" />
                </CustomColumn>

              </CustomRow>

            </CustomColumn>

            <CustomColumn customColumn="md-4">
              <CustomRow additionalStyles={["mb-1"]}>
                <CustomColumn customColumn="md-8">
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Notification option" 
                  />
                </CustomColumn>
              </CustomRow>

              <CustomRow>
                <CustomColumn customColumn="md-3" additionalStyles={["m-0"]}>
                  <Checkbox name="test" iconColor="green" checked={true} label="Recovery" />
                </CustomColumn>
                <CustomColumn customColumn="md-3" additionalStyles={["m-0"]}>
                  <Checkbox name="test" iconColor="green" checked={true} label="Warning" />
                </CustomColumn>
                <CustomColumn customColumn="md-3" additionalStyles={["m-0"]}>
                  <Checkbox name="test" iconColor="green" checked={true} label="Critical" />
                </CustomColumn>
                <CustomColumn customColumn="md-3" additionalStyles={["m-0"]}>
                  <Checkbox name="test" iconColor="green" checked={true} label="Flapping" />
                </CustomColumn>
              </CustomRow>

              <CustomRow>

                <CustomColumn customColumn="md-5" additionalStyles={["m-0"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="Enable notification" 
                  />
                </CustomColumn>

                <CustomColumn customColumn="md-5">
                  <CustomRow>
                    <CustomColumn customColumn="md-4">
                      <RadioButton name="test" iconColor="green" checked={true} label="YES" />
                    </CustomColumn>
                    <CustomColumn customColumn="md-4">
                      <RadioButton name="test" iconColor="green" label="NO" />
                    </CustomColumn>
                  </CustomRow>
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomStyles customStyles="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green"
              customClass="normal"
            />
          </CustomStyles>

        </Tab>

        <Tab label="Indicators">
          <CustomRow>

            <CustomColumn customColumn="md-2" additionalStyles={["center-vertical", "m-0"]}>
              <Subtitle label="Select status calculation method" subtitleType="bam" />
            </CustomColumn>

            <CustomColumn customColumn="md-2" additionalStyles={["m-0"]}>
              <InputFieldMultiSelect size="middle" />
            </CustomColumn>

            <CustomColumn customColumn="md-1" additionalStyles={["m-0"]}>
              <CustomButton label="Warning" color="orange" />
            </CustomColumn>

            <CustomColumn customColumn="md-1" additionalStyles={["m-0", "p-0", "center-both"]}>
              <IconInfo iconText="Treshold" />
            </CustomColumn>

            <CustomColumn customColumn="md-1" additionalStyles={["m-0", "p-0", "center-vertical"]}>
              <InputField 
                type="text"
                inputSize="smallest m-0" 
              />
            </CustomColumn>

            <CustomColumn customColumn="md-1" additionalStyles={["m-0"]}>
              <CustomButton label="Critical" color="red" />
            </CustomColumn>

            <CustomColumn customColumn="md-1" additionalStyles={["p-0", "center-both"]}>
              <IconInfo iconText="Treshold" />
            </CustomColumn>

            <CustomColumn customColumn="md-1" additionalStyles={["m-0", "p-0", "center-vertical"]}>
              <InputField 
                type="text"
                inputSize="smallest m-0" 
              />
            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-12" additionalStyles={["m-0"]}>
              <Subtitle label="Linked Resources" subtitleType="bam" />
            </CustomColumn>
          </CustomRow>

          <CustomRow additionalStyles={["mb-2"]}>
            <CustomColumn customColumn="md-3" additionalStyles={["center-vertical", "m-0"]}>
              <IconInfo iconText="Type of objects you want to calculate the result on" />
            </CustomColumn>
            <CustomColumn customColumn="md-2" additionalStyles={["center-vertical", "m-0"]}>
              <InputFieldMultiSelect size="small" />
            </CustomColumn>
          </CustomRow>
          <br />

          <CustomRow>
            <CustomColumn customColumn="md-12" additionalStyles={["m-0"]}>
              <SearchLive />
            </CustomColumn>
          </CustomRow>

          <CustomRow additionalStyles={["mt-1"]}>
            <CustomColumn customColumn="md-12" additionalStyles={["m-0", "p-0"]}>
              <ListSortable />
            </CustomColumn>
          </CustomRow>

          <CustomStyles customStyles="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green"
              customClass="normal"
            />
          </CustomStyles>
        </Tab>

        <Tab label="Reporting">
          <CustomRow additionalStyles={["mt-2"]}>
            <Subtitle label="Reporting" subtitleType="bam" />
          </CustomRow>

          <CustomRow>
            <InfoTooltip 
              iconColor="gray" 
              tooltipText="This is the an example of tooltip" 
              iconText="Select escalation that applied to this Business Activity" 
            />
          </CustomRow>

          <CustomRow additionalStyles={["mt-1", "mb-2"]}>
            <InputFieldMultiSelect size="medium" />
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomRow >
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>
              
              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA control percentage treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning duration treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomStyles customStyles="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green"
              customClass="normal"
            />
          </CustomStyles>
        </Tab>

        <Tab label="Escalation">
          <CustomRow additionalStyles={["mt-2"]}>
            <Subtitle label="Escalation" subtitleType="bam" />
          </CustomRow>

          <CustomRow>
            <InfoTooltip 
              iconColor="gray" 
              tooltipText="This is the an example of tooltip" 
              iconText="Extra reporting time periods used in Centreon BI reports" 
            />
          </CustomRow>

          <CustomRow additionalStyles={["mt-1", "mb-2"]}>
            <InputFieldMultiSelect size="medium" />
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomRow >
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>
              
              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA control percentage treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="(0-100%)" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning duration treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-5" additionalStyles={["p-0"]}>

              <CustomRow>
                <CustomColumn customColumn="md-5" additionalStyles={["m-0", "center-baseline"]}>
                  <InfoTooltip 
                    iconColor="gray" 
                    tooltipText="This is the an example of tooltip" 
                    iconText="SLA warning percentage treshold" 
                  />
                </CustomColumn>
                <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                  <InputField 
                    type="text"
                    inputSize="smallest m-0" 
                  />
                  <IconInfo iconText="minutes" />
                </CustomColumn>
              </CustomRow>

            </CustomColumn>
          </CustomRow>

          <CustomStyles customStyles="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green"
              customClass="normal"
            />
          </CustomStyles>
        </Tab>
        <Tab label="Event Handler">

          <CustomRow additionalStyles={["mt-2"]}>
            <Subtitle label="Event handler configuration" subtitleType="bam" />
          </CustomRow>

          <CustomRow>

            <CustomColumn customColumn="md-2" additionalStyles={["m-0", "p-0"]}>
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Enable event handler" 
              />
            </CustomColumn>

            <CustomColumn customColumn="md-2" additionalStyles={["m-0"]}>
              <div>
                <CustomColumn customColumn="md-4" additionalStyles={["m-0"]}>
                  <RadioButton name="test" iconColor="green" checked={true} label="YES" />
                </CustomColumn>
                <CustomColumn customColumn="md-4" additionalStyles={["m-0"]}>
                  <RadioButton name="test" iconColor="green" label="NO" />
                </CustomColumn>
              </div>
            </CustomColumn>
          </CustomRow>

          <CustomRow>
            <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical", "p-0"]}>
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Event handler command" 
              />
            </CustomColumn>
            <CustomColumn customColumn="md-2" additionalStyles={["m-0"]}>
              <InputFieldMultiSelect />
            </CustomColumn>
          </CustomRow>

          <CustomRow additionalStyles={["mt-1"]}>
            <CustomColumn customColumn="md-2" additionalStyles={["m-0", "p-0", "center-vertical"]}>
              <InfoTooltip 
                iconColor="gray" 
                tooltipText="This is the an example of tooltip" 
                iconText="Args" 
              />
            </CustomColumn>
            <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
              <InputField 
                type="text" 
                inputSize="big m-0" 
              />
            </CustomColumn>
            <CustomColumn customColumn="md-1" additionalStyles={["center-both"]}>
              <Button
                buttonType="validate"
                color="green"
                customClass="normal"
                customSecond="icon"
                iconActionType="arrow-left"
                iconColor="white"
              />
            </CustomColumn>
            <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
              <InputField 
                type="text" 
                inputSize="big m-0" 
              />
            </CustomColumn>
          </CustomRow>

          <CustomStyles customStyles="text-right">
            <Button
              label="SAVE"
              buttonType="validate"
              color="green"
              customClass="normal"
            />
          </CustomStyles>
        </Tab>
      </Tabs>
    </CustomStyles>
  </React.Fragment>
), {notes: "A very simple component"});