import React, { Component } from "react";
import InputFieldSelectTableCell from "../../InputField/InputFieldSelectTableCell";
import InputFieldTableCell from "../../InputField/InputFieldTableCell";
import StyledTableCell2 from "./StyledTableCell2";

class IndicatorsEditorRow extends Component {
  render() {
	const { row, index } = this.props;
	console.log(row)
    return (
      <React.Fragment>
        <StyledTableCell2 align="left">
          <InputFieldSelectTableCell
            options={[
              {
                id: "value",
                name: "Simple"
              },
              { id: "word", name: "Advanced" }
            ]}
            value={row.impact.type}
            active="active"
          />
        </StyledTableCell2>
      </React.Fragment>
    );
  }
}

export default IndicatorsEditorRow;
