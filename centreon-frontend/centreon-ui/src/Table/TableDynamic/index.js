import React, {Component} from 'react';
import InputFieldSelect from '../../InputField/InputFieldSelect';
import Checkbox from '../../Checkbox';
import SearchLive from '../../Search/SearchLive';
import ScrollBar from '../../ScrollBar';
import './table-dynamic.scss';

class TableDynamic extends Component {
  render() {
    return (
      <table class="table-dynamic">
        <thead>
          <tr>
            <th scope="col">
            <div className="container__row">
              <div className="container__col-md-3 center-vertical ml-1">
                <Checkbox label="ALL HOSTS" name="all-hosts" iconColor="white" />
              </div>
              <div className="container__col-md-6 center-vertical">
                <SearchLive />
              </div>
            </div>
            </th>
            <th scope="col"><InputFieldSelect customClass="medium" /></th>
          </tr>
        </thead>
        <tbody>
          <ScrollBar scrollType="big">
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
            <tr>
              <td>
                <Checkbox
                  label="Host 1 lorem ipsum dolor sit amet"
                  name="host1"
                  iconColor="light-blue"
                />
              </td>
              <td>
                <InputFieldSelect customClass="medium" />
              </td>
            </tr>
          </ScrollBar>
        </tbody>
      </table>
    );
  }
}

export default TableDynamic;