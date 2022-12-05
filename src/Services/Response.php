<?php namespace Ast\ViolatorsReport\Services;

class Response
{
    private $code = 0;
    private $data = [];
    private $popup = [
        'title' => 'Возникла ошибка',
        'msg' => 'Повторите позже',
    ];
    private $validateForm = [];

    public function success($msg = '', int $headerCode = 200)
    {
        $result = array_merge([
            'code' => $this->code,
            'msg' => $msg,
        ], $this->data);

        return response()->json($result, $headerCode);
    }

    public function fail($errors = '', int $headerCode = 500)
    {
        $this->setCode(1);

        $result = array_merge([
            'code' => $this->code,
            'errors' => $errors,
        ], $this->data);

        if ($this->popup && !$this->validateForm) {
            $result = array_merge($result, [
                'popup' => $this->popup
            ]);
        }

        if ($this->validateForm) {
            $result = array_merge($result, [
                'validate_form' => $this->validateForm
            ]);
        }

        return response()->json($result, $headerCode);
    }

    public function setCode(int $code)
    {
        $this->code = $code;

        return $this;
    }

    public function withoutPopup()
    {
        $this->popup = [];

        return $this;
    }

    public function setData(array $data)
    {
        $this->data = $data;

        return $this;
    }

    public function setPopup($title, $msg)
    {
        $this->popup = [
            'title' => $title,
            'msg' => $msg,
        ];

        return $this;
    }

    public function setValidateForm(array $validateForm)
    {
        $this->validateForm = $validateForm;

        return $this;
    }
}
