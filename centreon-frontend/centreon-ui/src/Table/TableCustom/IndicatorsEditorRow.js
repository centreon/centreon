import React, { Component } from "react";
import InputFieldSelectTableCell from "../../InputField/InputFieldSelectTableCell";
import InputFieldTableCell from "../../InputField/InputFieldTableCell";
import StyledTableCell2 from "./StyledTableCell2";

class IndicatorsEditorRow extends Component {
  onImpactChanged = (value, key, event) => {
    const { onImpactEdit, row } = this.props;
    let rowObject = row;
    if (key != "type") {
      rowObject.impact.type = rowObject.impact.type
        ? rowObject.impact.type
        : "word";
    }
    rowObject.impact[key] = value;
    onImpactEdit(event, rowObject, true);
  };

  onImpactInputChanged = (event, key) => {
    const value = event.target.value;
    const { onImpactEdit, row } = this.props;
    let rowObject = row;
    rowObject.impact[key] = value;
    onImpactEdit(event, rowObject, true);
  };
  render() {
    const { row, index, impacts, selected } = this.props;
    let rowMode = selected.bool
      ? (selected.obj.impact.type
      ? selected.obj.impact.type
      : "word")
      : row.impact.type
      ? row.impact.type
      : "word";
    return (
      <React.Fragment>
        <StyledTableCell2
          align="left"
          style={{
            padding: "3px 4px"
          }}
        >
          <InputFieldSelectTableCell
            options={[
              {
                id: "value",
                name: "Value"
              },
              { id: "word", name: "Words" }
            ]}
            active="active"
            size={"extrasmall"}
            disabled={!selected.bool}
            value={rowMode}
            onChange={(value, event) => {
              this.onImpactChanged(value, "type", event);
            }}
          />
        </StyledTableCell2>
        {rowMode == "word" ? (
          <React.Fragment>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldSelectTableCell
                options={impacts}
                value={
                  selected.bool
                    ? (selected.obj.impact.warning
                    ? selected.obj.impact.warning
                    : 1)
                    : row.impact.warning
                    ? row.impact.warning
                    : 1
                }
                isColored={true}
                size={"extrasmall"}
                active="active"
                disabled={!selected.bool}
                onChange={(value, event) => {
                  this.onImpactChanged(value, "warning", event);
                }}
              />
            </StyledTableCell2>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldSelectTableCell
                options={impacts}
                value={
                  selected.bool
                    ? (selected.obj.impact.critical
                    ? selected.obj.impact.critical
                    : 1 )
                    : row.impact.critical
                    ? row.impact.critical
                    : 1
                }
                isColored={true}
                size={"extrasmall"}
                active="active"
                disabled={!selected.bool}
                onChange={(value, event) => {
                  this.onImpactChanged(value, "critical", event);
                }}
              />
            </StyledTableCell2>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldSelectTableCell
                options={impacts}
                value={
                  selected.bool
                    ? (selected.obj.impact.unknown
                    ? selected.obj.impact.unknown
                    : 1)
                    : row.impact.unknown
                    ? row.impact.unknown
                    : 1
                }
                isColored={true}
                size={"extrasmall"}
                active="active"
                disabled={!selected.bool}
                onChange={(value, event) => {
                  this.onImpactChanged(value, "unknown", event);
                }}
              />
            </StyledTableCell2>
          </React.Fragment>
        ) : (
          <React.Fragment>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldTableCell
                value={
                  selected.bool
                    ? selected.obj.impact.warning
                    : row.impact.warning
                }
                inputSize={"extrasmall"}
                disabled={!selected.bool}
                onChange={event => {
                  this.onImpactInputChanged(event, "warning");
                }}
              />
            </StyledTableCell2>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldTableCell
                value={
                  selected.bool
                    ? selected.obj.impact.critical
                    : row.impact.critical
                }
                inputSize={"extrasmall"}
                disabled={!selected.bool}
                onChange={event => {
                  this.onImpactInputChanged(event, "critical");
                }}
              />
            </StyledTableCell2>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldTableCell
                value={
                  selected.bool
                    ? selected.obj.impact.unknown
                    : row.impact.unknown
                }
                inputSize={"extrasmall"}
                disabled={!selected.bool}
                onChange={event => {
                  this.onImpactInputChanged(event, "unknown");
                }}
              />
            </StyledTableCell2>
          </React.Fragment>
        )}
      </React.Fragment>
    );
  }
}

export default IndicatorsEditorRow;
