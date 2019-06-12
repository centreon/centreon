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
  CustomStyles,
  Breadcrumb,
  Divider,
  InputFieldSearch,
  ButtonCustom,
  TableCustom,
  IconDelete,
  IconLibraryAdd,
  IconPowerSettings,
  IconInsertChart,
  Panels
} from "../src";
import Paper from '@material-ui/core/Paper';

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
                  label="Plugin Packs Manager"
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
                  label="Plugin Packs Manager"
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
                  label="Plugin Packs Manager"
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
                  label="Plugin Packs Manager"
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
      <HorizontalLineContent hrColor="blue" hrTitleColor="blue" hrTitle="Widgets"/>
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
  <CustomStyles additionalStyles={["content-wrap"]}>
    <CustomStyles additionalStyles={["content-inner"]}>
      <CustomStyles additionalStyles={["content-overflow"]}>
        {/* <Title style={{padding: '10px'}} titleColor="bam" label="BAM Corelations Capabilities" /> */}
        <CustomStyles>
          <CustomStyles additionalStyles={["container", "p-0"]}>
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
                  <CustomColumn customColumn="xl-3" additionalColumns={["lg-4"]}>
                    <InfoTooltip 
                      iconColor="gray" 
                      tooltipText="Name of business activity" 
                      iconText="Name" 
                    />
                    <InputField 
                      error="The field is mandatory" 
                      inputSize="middle" 
                    />
                    <InfoTooltip 
                      iconColor="gray" 
                      tooltipText="Description of business activity" 
                      iconText="Description" 
                    />
                    <InputFieldTextarea 
                      textareaType="middle" 
                    />
                  </CustomColumn>

                  <CustomColumn customColumn="xxl-4" additionalColumns={["xl-5"]}>

                  <CustomRow additionalStyles={["mb-2"]}>

                    <CustomColumn customColumn="xl-2" additionalColumns={["md-2"]} additionalStyles={["m-0", "center-vertical"]}>
                      <CustomButton label="Warning" color="orange" />
                    </CustomColumn>

                    <CustomColumn customColumn="xl-2" additionalColumns={["md-2"]} additionalStyles={["m-0", "p-0", "center-both"]}>
                      <IconInfo iconText="Threshold" />
                    </CustomColumn>

                    <CustomColumn customColumn="xl-2" additionalColumns={["md-2"]} additionalStyles={["m-0", "p-0", "center-vertical"]}>
                      <InputField 
                        type="text"
                        inputSize="smallest"
                        noMargin="no-bottom-margin"
                      />
                    </CustomColumn>

                    <CustomColumn customColumn="xl-2" additionalColumns={["md-2"]} additionalStyles={["m-0", "center-vertical"]}>
                      <CustomButton label="Critical" color="red" />
                    </CustomColumn>

                    <CustomColumn customColumn="xl-2" additionalColumns={["md-2"]} additionalStyles={["p-0", "m-0", "center-both"]}>
                      <IconInfo iconText="Threshold" />
                    </CustomColumn>

                    <CustomColumn customColumn="xl-2" additionalColumns={["md-2"]} additionalStyles={["m-0", "p-0", "center-vertical"]}>
                      <InputField 
                        type="text"
                        inputSize="smallest"
                        noMargin="no-bottom-margin"
                      />
                    </CustomColumn>
                  </CustomRow>

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-3"]} additionalStyles={["center-vertical", "m-0"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Icon that represents the business activity" 
                          iconText="Icon" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-6">
                        <InputFieldMultiSelect customClass="medium" />
                      </CustomColumn>
                    </CustomRow>

                    <br />
                    <CustomRow>

                      <CustomColumn customColumn="xl-5" additionalColumns={["md-4"]} additionalStyles={["m-0"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="(Broker >= 3) Whether or not the business activity has to inherit planned downtimes from its KPI. See documentation for more information on the feature." 
                          iconText="Automatically inherit KPI downtime" 
                        />
                      </CustomColumn>

                      <CustomColumn customColumn="md-5">
                        <CustomRow>
                          <CustomColumn customColumn="xl-4" additionalColumns={["md-4"]}>
                            <RadioButton name="test" iconColor="blue" checked={true} label="YES" />
                          </CustomColumn>
                          <CustomColumn customColumn="xl-4" additionalColumns={["md-4"]}>
                            <RadioButton name="test" iconColor="blue" label="NO" />
                          </CustomColumn>
                        </CustomRow>
                      </CustomColumn>

                    </CustomRow>

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-3"]} additionalStyles={["center-vertical", "m-0"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Possibility to display this Business Activity on a Remote Server which have Centreon BAM module installed" 
                          iconText="Display on remote server         " 
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
                        tooltipText="Business view(s) of this business activity" 
                        iconText="Link to Business View(s)" 
                      />
                    </div>
                    <CustomRow>
                      <CustomColumn customColumn="xs-11">
                        <InputFieldMultiSelect size="medium" />
                      </CustomColumn>
                    </CustomRow>
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
                  <CustomColumn customColumn="xl-3" additionalColumns={["md-4"]}>
                    <div>
                      <InfoTooltip 
                        iconColor="gray" 
                        tooltipText="Contact groups authorized to receive notifications from this Business Activity" 
                        iconText="Contact groups authorized to receive notifications from this Business Activity" 
                      />
                    </div>
                    <InputFieldMultiSelect size="medium" />
                  </CustomColumn>

                  <CustomColumn customColumn="xl-4">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-6"]} additionalStyles={["center-vertical", "m-0"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Time period during which notification can take place" 
                          iconText="Notification time period" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-6" additionalStyles={["m-0"]}>
                        <InputFieldMultiSelect size="big" />
                      </CustomColumn>
                    </CustomRow>

                    <br />
                    <CustomRow>

                      <CustomColumn customColumn="xl-4"  additionalColumns={["md-6"]} additionalStyles={["center-vertical", "m-0"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Notification interval length" 
                          iconText="Notification interval" 
                        />
                      </CustomColumn>

                      <CustomColumn customColumn="md-6" additionalStyles={["center-baseline", "m-0"]}>
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
                          tooltipText="States for which notifications will be sent out" 
                          iconText="Notification options" 
                        />
                      </CustomColumn>
                    </CustomRow>

                    <CustomRow>
                      <CustomColumn customColumn="xl-2" additionalColumns={["md-3"]} additionalStyles={["m-0"]}>
                        <Checkbox name="test" iconColor="light-blue" checked={true} label="Recovery" />
                      </CustomColumn>
                      <CustomColumn customColumn="xl-2" additionalColumns={["md-3"]} additionalStyles={["m-0"]}>
                        <Checkbox name="test" iconColor="light-blue" checked={true} label="Warning" />
                      </CustomColumn>
                      <CustomColumn customColumn="xl-2" additionalColumns={["md-3"]} additionalStyles={["m-0"]}>
                        <Checkbox name="test" iconColor="light-blue" checked={true} label="Critical" />
                      </CustomColumn>
                      <CustomColumn customColumn="xl-2" additionalColumns={["md-3"]} additionalStyles={["m-0"]}>
                        <Checkbox name="test" iconColor="light-blue" checked={true} label="Flapping" />
                      </CustomColumn>
                    </CustomRow>

                    <CustomRow additionalStyles={["mt-1"]}>

                      <CustomColumn customColumn="xl-3" additionalColumns={["md-5"]} additionalStyles={["center-vertical"]}>
                        <Subtitle label="Enable notification" subtitleType="bam" />
                      </CustomColumn>

                      <CustomColumn customColumn="md-6"  additionalStyles={["m-0", "center-baseline"]}>
                        <SwitcherInputField />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomStyles>
                  <CustomRow>
                    <CustomColumn customColumn="xl-10" additionalColumns={["md-11"]}>
                      <Button
                        label="SAVE"
                        buttonType="validate"
                        color="blue"
                        customClass="normal"
                      />
                    </CustomColumn>
                  </CustomRow>
                </CustomStyles>

              </Tab>

              <Tab label="Indicators">
                <CustomRow>

                  <CustomColumn customColumn="xxl-1" additionalColumns={["xl-2"]} additionalStyles={["center-vertical", "m-0"]}>
                    <Subtitle label="Select status calculation method" subtitleType="bam" />
                  </CustomColumn>

                  <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
                    <InputFieldMultiSelect />
                  </CustomColumn>

                  <CustomRow>

                    <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
                      <CustomButton label="Warning" color="orange" />
                    </CustomColumn>

                    <CustomColumn customColumn="md-2" additionalColumns={["md-2"]} additionalStyles={["m-0", "center-both"]}>
                      <IconInfo iconText="Threshold" />
                    </CustomColumn>

                    <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
                      <InputField 
                        type="text"
                        inputSize="smallest"
                        noMargin="no-bottom-margin" 
                      />
                    </CustomColumn>

                    <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
                      <CustomButton label="Critical" color="red" />
                    </CustomColumn>

                    <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-both"]}>
                      <IconInfo iconText="Threshold" />
                    </CustomColumn>

                    <CustomColumn customColumn="md-2" additionalStyles={["m-0", "p-0", "center-vertical"]}>
                      <InputField 
                        type="text"
                        inputSize="smallest"
                        noMargin="no-bottom-margin"
                      />
                    </CustomColumn>
                  </CustomRow>
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

                <CustomStyles>
                  <CustomRow>
                    <CustomColumn customColumn="md-12">
                      <Button
                        label="SAVE"
                        buttonType="validate"
                        color="blue"
                        customClass="normal"
                      />
                    </CustomColumn>
                  </CustomRow>
                </CustomStyles>
              </Tab>

              <Tab label="Reporting">
                <CustomRow additionalStyles={["mt-2"]}>
                  <CustomColumn customColumn="xs-12">
                    <Subtitle label="Reporting" subtitleType="bam" />
                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="xs-12">
                    <InfoTooltip 
                      iconColor="gray" 
                      tooltipText="Those time periods will be used by Centreon BI reports." 
                      iconText="Extra reporting time periods used in Centreon BI reports" 
                    />
                  </CustomColumn>
                </CustomRow>

                <CustomRow additionalStyles={["mt-1", "mb-2"]}>
                  <CustomColumn customColumn="xs-12">
                    <InputFieldMultiSelect size="middle" />
                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="md-5">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="SLA warning percentage threshold" 
                          iconText="SLA warning percentage threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="(0-100%)" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomRow >
                  <CustomColumn customColumn="md-5">
                    
                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Percentage of time during which the BA was in a Critical status on a monthly basis" 
                          iconText="SLA critical percentage threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="(0-100%)" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="md-5">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Amount of time during which the BA was in a Warning status on a monthly basis" 
                          iconText="SLA warning duration threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="minutes" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="md-5">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="SLA critical duration threshold" 
                          iconText="SLA critical duration threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="minutes" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomStyles>
                  <CustomRow>
                    <CustomColumn customColumn="xl-3" additionalColumns={["md-4"]}>
                      <Button
                        label="SAVE"
                        buttonType="validate"
                        color="blue"
                        customClass="normal"
                      />
                    </CustomColumn>
                  </CustomRow>
                </CustomStyles>
              </Tab>

              <Tab label="Escalation">
                <CustomRow additionalStyles={["mt-2"]}>
                  <CustomColumn customColumn="xs-12">
                    <Subtitle label="Escalation" subtitleType="bam" />
                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="xs-12">
                    <InfoTooltip 
                      iconColor="gray" 
                      tooltipText="Escalations rules that are applied to this business activity" 
                      iconText="Select escalation rules that are applied to this Business Activity" 
                    />
                  </CustomColumn>
                </CustomRow>

                <CustomRow additionalStyles={["mt-1", "mb-2"]}>
                  <CustomColumn customColumn="xs-12">
                    <InputFieldMultiSelect size="middle" />
                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="md-5">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="SLA warning percentage threshold" 
                          iconText="SLA warning percentage threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="(0-100%)" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomRow >
                  <CustomColumn customColumn="md-5">
                    
                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Percentage of time during which the BA was in a Critical status on a monthly basis" 
                          iconText="SLA control percentage threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="(0-100%)" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="md-5">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="Amount of time during which the BA was in a Warning status on a monthly basis" 
                          iconText="SLA warning duration threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="minutes" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="md-5">

                    <CustomRow>
                      <CustomColumn customColumn="xl-4" additionalColumns={["md-5"]} additionalStyles={["m-0", "center-baseline"]}>
                        <InfoTooltip 
                          iconColor="gray" 
                          tooltipText="SLA critical duration threshold" 
                          iconText="SLA critical percentage threshold" 
                        />
                      </CustomColumn>
                      <CustomColumn customColumn="md-7" additionalStyles={["m-0", "center-baseline"]}>
                        <InputField 
                          type="text"
                          inputSize="smallest" 
                        />
                        <IconInfo iconText="minutes" />
                      </CustomColumn>
                    </CustomRow>

                  </CustomColumn>
                </CustomRow>

                <CustomStyles>
                  <CustomRow>
                    <CustomColumn customColumn="xl-3" additionalColumns={["md-4"]}>
                      <Button
                        label="SAVE"
                        buttonType="validate"
                        color="blue"
                        customClass="normal"
                      />
                    </CustomColumn>
                  </CustomRow>
                </CustomStyles>
              </Tab>
              <Tab label="Event Handler">

                <CustomRow additionalStyles={["mt-2"]}>
                  <CustomColumn customColumn="xs-12">
                    <Subtitle label="Event handler configuration" subtitleType="bam" />
                  </CustomColumn>
                </CustomRow>

                <CustomRow>

                  <CustomColumn customColumn="xl-1" additionalColumns={["md-2"]} additionalStyles={["m-0", "p-0"]}>
                    <CustomColumn customColumn="xs-12">
                      <InfoTooltip 
                        iconColor="gray" 
                        tooltipText="Enable event handler" 
                        iconText="Enable event handler" 
                      />
                    </CustomColumn>
                  </CustomColumn>

                  <CustomColumn customColumn="xl-5" additionalColumns={["md-3"]} additionalStyles={["m-0"]}>
                    <CustomRow>
                      <CustomColumn customColumn="xl-1" additionalColumns={["md-2"]} additionalStyles={["m-0"]}>
                        <RadioButton name="test" iconColor="light-blue" checked={true} label="YES" />
                      </CustomColumn>
                      <CustomColumn customColumn="md-1" additionalColumns={["md-2"]} additionalStyles={["m-0"]}>
                        <RadioButton name="test" iconColor="light-blue" label="NO" />
                      </CustomColumn>
                    </CustomRow>
                  </CustomColumn>
                </CustomRow>

                <CustomRow>
                  <CustomColumn customColumn="xl-1" additionalColumns={["md-2"]} additionalStyles={["m-0", "center-vertical"]}>
                    <InfoTooltip 
                      iconColor="gray" 
                      tooltipText="Event handler command" 
                      iconText="Event handler command" 
                    />
                  </CustomColumn>
                  <CustomColumn customColumn="md-2" additionalStyles={["m-0"]}>
                    <InputFieldMultiSelect />
                  </CustomColumn>
                </CustomRow>

                <CustomRow additionalStyles={["mt-1"]}>
                  <CustomColumn customColumn="xl-1" additionalColumns={["md-2"]} additionalStyles={["m-0", "center-vertical"]}>
                    <InfoTooltip 
                      iconColor="gray" 
                      tooltipText="Args" 
                      iconText="Args" 
                    />
                  </CustomColumn>
                  <CustomColumn customColumn="md-2" additionalStyles={["m-0", "center-vertical"]}>
                    <InputField 
                      type="text" 
                      inputSize="big" 
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

                <CustomStyles>
                  <CustomRow>
                    <CustomColumn customColumn="xl-6" additionalColumns={["md-7"]}>
                      <Button
                        label="SAVE"
                        buttonType="validate"
                        color="blue"
                        customClass="normal"
                      />
                    </CustomColumn>
                  </CustomRow>
                </CustomStyles>
              </Tab>
            </Tabs>
            </CustomStyles>
          </CustomStyles>
      </CustomStyles>
    </CustomStyles>
  </CustomStyles>
), {notes: "A very simple component"});

storiesOf("Pages", module).add("BAM page", 
() => {
  return (
  <React.Fragment>
    <Breadcrumb />
    <Divider />
    <Paper elevation={0} style={{overflow: 'hidden', padding: '8px 16px'}}>
      <CustomRow>
        <CustomColumn customColumn="md-4" additionalStyles={["flex-none", "container__col-xs-12", "m-0", "mr-2"]}>
          <InputFieldSearch />
        </CustomColumn>
        <CustomColumn customColumn="md-4" additionalStyles={["flex-none", "container__col-xs-12", "m-0"]}>
        <ButtonCustom label="ADD" />
        </CustomColumn>
      </CustomRow>
    </Paper>
    <Divider />
    <Paper elevation={0} style={{padding: '8px 16px'}}>
      <CustomRow>
        <CustomColumn customColumn="md-3" additionalStyles={["flex-none", "container__col-xs-12"]}>
          <IconDelete label="Delete" />
        </CustomColumn>
        <CustomColumn customColumn="md-3" additionalStyles={["flex-none", "container__col-xs-12"]}>
          <IconLibraryAdd label="Duplicate" />
        </CustomColumn>
        <CustomColumn customColumn="md-3" additionalStyles={["flex-none", "container__col-xs-12"]}>
          <IconInsertChart />
        </CustomColumn>
        <CustomColumn customColumn="md-3" additionalStyles={["flex-none", "container__col-xs-12"]}>
          <IconPowerSettings customStyle={{backgroundColor: '#009fdf', marginTop: 2}} label="Enable/Disable"/>
        </CustomColumn>
      </CustomRow>
    </Paper>
    <Paper elevation={0} style={{padding: '8px 16px', paddingTop: 0}}>
      <TableCustom />
    </Paper>
  </React.Fragment>)},
  {notes: "A very simple component"}
);