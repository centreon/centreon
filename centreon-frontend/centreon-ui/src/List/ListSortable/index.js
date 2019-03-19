import React, {Component} from 'react';
import Checkbox from '../../Checkbox';
import InputFieldSelect from '../../InputField/InputFieldSelect';
import './list-sortable.scss';
import { SwitcherMode } from '../..';

class ListSortable extends Component {
  render() {
    return (
      <table class="list list-sortable">
        <thead>
          <tr>
            <th scope="col">INDICATORS</th>
            <th scope="col">TYPE</th>
            <th scope="col">DEFINE IMPACT</th>
            <th scope="col">WARNING</th>
            <th scope="col">CRITICAL</th>
            <th scope="col">UNKOWN</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <Checkbox label="Lorem Ipsum dolor sit amet" name="all-hosts" iconColor="green" />
            </td>
            <td>
              Type 1
            </td>
            <td>
              <SwitcherMode />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox label="Lorem Ipsum dolor sit amet" name="all-hosts" iconColor="green" />
            </td>
            <td>
              Type 2
            </td>
            <td>
              <SwitcherMode />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
          </tr>
          <tr>
            <td>
              <Checkbox label="Lorem Ipsum dolor sit amet" name="all-hosts" iconColor="green" />
            </td>
            <td>
              Type 3
            </td>
            <td>
              <SwitcherMode />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
            <td>
              <InputFieldSelect customClass="small" />
            </td>
          </tr>
        </tbody>
      </table>
    );
  }
}

export default ListSortable;