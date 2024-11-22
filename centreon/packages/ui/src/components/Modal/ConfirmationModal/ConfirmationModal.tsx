import { useMemo } from 'react';

import { PrimitiveAtom, useAtom } from 'jotai';
import { equals, pick, type } from 'ramda';

import { Modal } from '..';

export interface ConfirmationModalProps<TAtom> {
  atom: PrimitiveAtom<string | null>;
  disabled?: boolean;
  hasCloseButton?: boolean;
  isDanger?: boolean;
  labels: {
    cancel: string | ((atom: Awaited<TAtom> | null) => string);
    confirm: string | ((atom: Awaited<TAtom> | null) => string);
    description: string | ((atom: Awaited<TAtom> | null) => string);
    title: string | ((atom: Awaited<TAtom> | null) => string);
  };
  onCancel?: (atomData: Awaited<TAtom> | null) => void;
  onClose?: (atomData: Awaited<TAtom> | null) => void;
  onConfirm?: (atomData: Awaited<TAtom> | null) => void;
}

interface GetLabelProps<TAtom> {
  atomData: Awaited<TAtom> | null;
  label: string | ((atom: Awaited<TAtom> | null) => string);
}

const getLabel = <TAtom,>({ label, atomData }: GetLabelProps<TAtom>): string =>
  equals(type(label), 'String')
    ? (label as string)
    : (label as (atom: Awaited<TAtom> | null) => string)(atomData);

export const ConfirmationModal = <TAtom,>({
  atom,
  labels,
  onConfirm,
  onCancel,
  onClose,
  hasCloseButton = true,
  isDanger,
  disabled
}: ConfirmationModalProps<TAtom>): JSX.Element => {
  const [atomData, setAtomData] = useAtom<TAtom | null>(atom);

  const closeModal = (): void => {
    onClose?.(atomData);
    setAtomData(null);
  };

  const formattedLabels = useMemo(() => {
    return {
      cancel: getLabel({ atomData, label: labels.cancel }),
      confirm: getLabel({ atomData, label: labels.confirm }),
      description: getLabel({ atomData, label: labels.description }),
      title: getLabel({ atomData, label: labels.title })
    };
  }, [labels, atomData]);

  const confirm = (): void => {
    onConfirm?.(atomData);
    setAtomData(null);
  };

  const cancel = (): void => {
    onCancel?.(atomData);
    setAtomData(null);
  };

  return (
    <Modal
      hasCloseButton={hasCloseButton}
      open={Boolean(atomData)}
      onClose={closeModal}
    >
      <Modal.Header>{formattedLabels.title}</Modal.Header>
      <Modal.Body>{formattedLabels.description}</Modal.Body>
      <Modal.Actions
        disabled={disabled}
        isDanger={isDanger}
        labels={pick(['confirm', 'cancel'], formattedLabels)}
        onCancel={cancel}
        onConfirm={confirm}
      />
    </Modal>
  );
};
