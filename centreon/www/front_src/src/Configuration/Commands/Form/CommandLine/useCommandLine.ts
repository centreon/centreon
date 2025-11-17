import { useFormikContext } from 'formik';
import { ChangeEvent, RefObject, useRef, useState } from 'react';

import { Command, NamedEntity } from '../../models';

interface UseCommandLineState {
  macros: {
    globalMarco: NamedEntity | null;
    standardMacro: NamedEntity | null;
    installedPlugin: NamedEntity | null;
  };
  changeCommand: (event: ChangeEvent<HTMLInputElement>) => void;
  changeMacro: (property: string) => (_, value) => void;
  insertMacroIntoCommand: (property: string) => () => void;
  textFieldRef: RefObject<HTMLInputElement | null>;
  error: string | boolean;
  values: Command;
}

export const useCommandLine = (): UseCommandLineState => {
  const textFieldRef = useRef<HTMLInputElement>(null);
  const [macros, setMacros] = useState({
    globalMarco: null,
    standardMacro: null,
    installedPlugin: null
  });

  const { values, setFieldValue, setFieldTouched, touched, errors } =
    useFormikContext<Command>();

  const changeCommand = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('commandLine', true);
    setFieldValue('commandLine', event.target.value);
  };

  const changeMacro =
    (property: string) =>
    (_, value): void => {
      setMacros({ ...macros, [property]: value });
    };

  const insertMacroIntoCommand = (property: string) => (): void => {
    const macro = macros[property].name;
    const commandLine = values.commandLine;

    const cursorPosition =
      textFieldRef.current?.selectionStart ?? commandLine.length;

    const newCommandLine =
      commandLine.slice(0, cursorPosition) +
      macro +
      commandLine.slice(cursorPosition);

    setFieldTouched('commandLine', true);
    setFieldValue('commandLine', newCommandLine);

    setTimeout(() => {
      if (textFieldRef.current) {
        textFieldRef.current.focus();

        const newCursorPosition = cursorPosition + macro.length;

        textFieldRef.current.setSelectionRange(
          newCursorPosition,
          newCursorPosition
        );
      }
    }, 0);
  };

  return {
    macros,
    changeCommand,
    changeMacro,
    insertMacroIntoCommand,
    textFieldRef,
    error: touched?.commandLine && errors?.commandLine,
    values
  };
};
