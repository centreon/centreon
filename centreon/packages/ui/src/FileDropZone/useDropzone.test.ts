import { act, renderHook, RenderHookResult } from '@testing-library/react';

import { labelFileTooBig, labelInvalidFileType } from './translatedLabels';
import useDropzone, { UseDropzoneProps, UseDropzoneState } from './useDropzone';

const mockChangeFiles = jest.fn();
const mockResetFilesStatusAndUploadData = jest.fn();
const mockClick = jest.fn();

const renderUseFileDropzone = ({
  allowedFilesExtensions,
  maxFileSize
}: Pick<
  UseDropzoneProps,
  'allowedFilesExtensions' | 'maxFileSize'
>): RenderHookResult<UseDropzoneProps, UseDropzoneState> =>
  renderHook(() =>
    useDropzone({
      allowedFilesExtensions,
      changeFiles: mockChangeFiles,
      maxFileSize,
      resetFilesStatusAndUploadData: mockResetFilesStatusAndUploadData
    })
  );

const createFileList = (files: Array<File>): FileList => {
  const fileList = {
    item: (index): File | undefined => files[index],
    length: files.length
  } as FileList;

  return fileList;
};

const file = new File(['(⌐□_□)'], 'example.png', { type: 'image/png' });
const otherFile = new File(['(⌐□_□)'], 'example2.png', { type: 'image/png' });
const bigFile = new File(['(⌐□_□)'], 'example.png', { type: 'image/png' });
Object.defineProperty(bigFile, 'size', { value: 1024 });

describe('useDropzone', () => {
  beforeEach(() => {
    mockChangeFiles.mockReset();
    mockResetFilesStatusAndUploadData.mockReset();
    mockClick.mockReset();
  });

  it('changes the files when the "handleChangeFiles" function is called and the file extension is valid', () => {
    const { result } = renderUseFileDropzone({
      allowedFilesExtensions: ['png']
    });

    const fileList = createFileList([file]);

    act(() => {
      result.current.handleChangeFiles({ target: { files: fileList } });
    });

    expect(mockChangeFiles).toHaveBeenCalledWith(fileList);
    expect(mockResetFilesStatusAndUploadData).toBeCalled();
    expect(result.current.error).toEqual(null);
  });

  it('changes the files when the "dropFiles" function is called and the file extension is valid', () => {
    const { result } = renderUseFileDropzone({
      allowedFilesExtensions: ['png']
    });

    const fileList = createFileList([file]);

    act(() => {
      result.current.dropFiles({
        dataTransfer: { files: fileList },
        preventDefault: jest.fn(),
        stopPropagation: jest.fn()
      });
    });

    expect(mockChangeFiles).toHaveBeenCalledWith(fileList);
    expect(mockResetFilesStatusAndUploadData).toBeCalled();
    expect(result.current.error).toEqual(null);
  });

  it('does not change the files and returns an error function when "handleChangeFiles" is called and the file extension is not valid', () => {
    const { result } = renderUseFileDropzone({
      allowedFilesExtensions: ['jpg']
    });

    const fileList = createFileList([file]);

    act(() => {
      result.current.handleChangeFiles({ target: { files: fileList } });
    });

    expect(mockChangeFiles).toHaveBeenCalledWith(null);
    expect(mockResetFilesStatusAndUploadData).not.toBeCalled();
    expect(result.current.error).toEqual(labelInvalidFileType);
  });

  it('does not change the files and returns an error function when "handleChangeFiles" is called and the file size is too big', () => {
    const { result } = renderUseFileDropzone({
      allowedFilesExtensions: ['png'],
      maxFileSize: 100
    });

    const fileList = createFileList([bigFile]);

    act(() => {
      result.current.handleChangeFiles({ target: { files: fileList } });
    });

    expect(mockChangeFiles).toHaveBeenCalledWith(null);
    expect(mockResetFilesStatusAndUploadData).not.toBeCalled();
    expect(result.current.error).toEqual(labelFileTooBig);
  });

  it('gets the file name', () => {
    const { result } = renderUseFileDropzone({
      allowedFilesExtensions: ['png']
    });

    const fileList = createFileList([file, otherFile]);

    expect(result.current.getFilesName(fileList)).toEqual([
      'example.png',
      'example2.png'
    ]);
  });

  it('opens the file explorer when the "openFileExplorer" function is called', () => {
    const { result } = renderUseFileDropzone({
      allowedFilesExtensions: ['png']
    });

    act(() => {
      result.current.fileInputRef.current = { click: mockClick };
      result.current.openFileExplorer();
    });

    expect(mockClick).toHaveBeenCalled();
  });
});
