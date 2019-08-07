import React, { Component } from "react";
import InputFieldSelectTableCell from "../../InputField/InputFieldSelectTableCell";
import InputFieldTableCell from "../../InputField/InputFieldTableCell";
import StyledTableCell2 from "./StyledTableCell2";

class IndicatorsEditorRow extends Component {
  render() {
    const { row, index, impacts, selected } = this.props;
    let rowMode = row.impact.type ? row.impact.type : "word";
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
            value={rowMode}
            active="active"
            size={"extrasmall"}
            disabled={!selected}
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
                value={row.impact.warning ? row.impact.warning : 1}
                isColored={true}
                size={"extrasmall"}
                active="active"
                disabled={!selected}
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
                value={row.impact.critical ? row.impact.critical : 1}
                isColored={true}
                size={"extrasmall"}
                active="active"
                disabled={!selected}
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
                value={row.impact.unknown ? row.impact.unknown : 1}
                isColored={true}
                size={"extrasmall"}
                active="active"
                disabled={!selected}
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
                value={row.impact.warning}
                inputSize={"extrasmall"}
                disabled={!selected}
              />
            </StyledTableCell2>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldTableCell
                value={row.impact.critical}
                inputSize={"extrasmall"}
                disabled={!selected}
              />
            </StyledTableCell2>
            <StyledTableCell2
              align="left"
              style={{
                padding: "3px 4px"
              }}
            >
              <InputFieldTableCell
                value={row.impact.unknown}
                inputSize={"extrasmall"}
                disabled={!selected}
              />
            </StyledTableCell2>
          </React.Fragment>
        )}
      </React.Fragment>
    );
  }
}

export default IndicatorsEditorRow;
