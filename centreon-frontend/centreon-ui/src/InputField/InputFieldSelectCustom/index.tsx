/* eslint-disable jsx-a11y/click-events-have-key-events */
/* eslint-disable jsx-a11y/no-static-element-interactions */

import React, { useState, useEffect, useRef, ChangeEvent } from 'react';
import clsx from 'clsx';
import styles from './input-field.scss';
import CustomIconWithText from '../../Custom/CustomIconWithText';
import IconToggleSubmenu from '../../Icon/IconToggleSubmenu';

interface Option {
  id: number;
  name: string;
  preview?: string;
  alias?: string;
}

interface Props {
  options: Array<Option>;
  value?: Option;
  disabled?: boolean;
  onChange?: (option: Option) => void;
  ariaLabel?: string;
  size?: string;
  error?: string;
  icons?: boolean;
  domainPath?: string;
  customStyle?: string;
}

const InputFieldSelectCustom = ({
  options,
  value,
  disabled,
  onChange,
  ariaLabel,
  size,
  error,
  icons,
  domainPath,
  customStyle,
}: Props): JSX.Element => {
  const [active, setActive] = useState<boolean>(false);
  const [filteredOptions, setFilteredOptions] = useState<Array<Option>>(
    options,
  );
  const [selected, setSelected] = useState<Option | null | undefined>(null);
  const focusInput = useRef<HTMLInputElement>(null);
  const selectComponent = useRef<HTMLDivElement>(null);

  const toggleSelect = (): void => {
    if (disabled) return;
    setActive(!active);
  };

  const handleClickOutside = (e): void => {
    if (selectComponent.current?.contains(e.target)) return;
    setActive(false);
  };

  const optionChecked = (option): void => {
    setActive(false);
    setSelected(option);
    if (options.length !== filteredOptions.length) {
      setFilteredOptions(options);
    }
    if (onChange) {
      onChange(option);
    }
  };

  const searchTextChanged = (e: ChangeEvent<HTMLInputElement>): void => {
    const searchString = e.target.value;
    const newOptions = options.filter((option) => {
      return option.name.includes(searchString);
    });
    setFilteredOptions(newOptions);
  };

  useEffect(() => {
    window.addEventListener('mousedown', handleClickOutside);
    return (): void => {
      window.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  useEffect((): void => {
    if (active && focusInput.current) {
      focusInput.current.focus();
    }
  }, [active]);

  useEffect((): void => {
    setSelected(value);
  }, [value]);

  return (
    <div
      className={clsx(
        styles['input-select'],
        styles[size || ''],
        styles[active ? 'active' : ''],
        error ? styles['has-danger'] : '',
        customStyle ? styles[customStyle] : '',
      )}
      ref={selectComponent}
    >
      <div className={clsx(styles['input-select-wrap'])}>
        {active ? (
          <input
            ref={focusInput}
            onChange={searchTextChanged}
            className={clsx(styles['input-select-input'])}
            type="text"
            placeholder="Search"
          />
        ) : (
          <span
            className={clsx(styles['input-select-field'])}
            onClick={toggleSelect}
            aria-label={ariaLabel}
          >
            {selected ? selected.name : ''}
          </span>
        )}
        <IconToggleSubmenu
          iconPosition="icons-toggle-position-multiselect"
          iconType="arrow"
          onClick={toggleSelect}
        />
      </div>
      {active ? (
        <div className={clsx(styles['input-select-dropdown'])}>
          {filteredOptions
            ? filteredOptions.map((option) => (
                // eslint-disable-next-line react/jsx-indent
                <div key={option.id}>
                  {icons ? (
                    <CustomIconWithText
                      label={option.name}
                      onClick={(): void => {
                        optionChecked(option);
                      }}
                      {...(option.preview
                        ? { image: `${domainPath}/${option.preview}` }
                        : { iconOff: true })}
                    />
                  ) : (
                    <span
                      onClick={(): void => {
                        optionChecked(option);
                      }}
                      className={clsx(styles['input-select-label'])}
                    >
                      {option.name}
                    </span>
                  )}
                </div>
              ))
            : null}
        </div>
      ) : null}
      {error ? <div className={clsx(styles['form-error'])}>{error}</div> : null}
    </div>
  );
};

export default InputFieldSelectCustom;
