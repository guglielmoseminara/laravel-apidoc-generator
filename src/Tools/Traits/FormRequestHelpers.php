<?php

namespace Mpociot\ApiDoc\Tools\Traits;

use Mpociot\ApiDoc\Tools\RuleDescriptionParser as Description;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Faker\Factory;

trait FormRequestHelpers
{
    public function getParams($rules, $formRequest)
    {
        $params = [];
        $validator = Validator::make([], $rules);
        $rules = array_map(function($rule){
            return explode('|', $rule);
        }, $rules);
        foreach ($rules as $attribute => $rules) {
            $attributeData = [
                'required' => false,
                'type' => null,
                'default' => '',
                'value' => '',
                'description' => [],
            ];
            foreach ($rules as $ruleName => $rule) {
                $this->parseRule($rule, $attribute, $attributeData, $formRequest);
            }
            $params[$attribute] = $attributeData;
        }
        return $params;
    }

    protected function splitValuePairs($parameters, $first = 'is ', $last = 'or ')
    {
        $attribute = '';
        collect($parameters)->map(function ($item, $key) use (&$attribute, $first, $last) {
            $attribute .= '`' . $item . '` ';
            if (($key + 1) % 2 === 0) {
                $attribute .= $last;
            } else {
                $attribute .= $first;
            }
        });
        $attribute = rtrim($attribute, $last);
        return $attribute;
    }

    /**
     * @param  string  $rule
     * @param  array  $attributeData
     *
     * @return void
     */
    protected function parseRule($rule, $ruleName, &$attributeData, $seed)
    {
        $faker = Factory::create();
        $parsedRule = $this->parseStringRule($rule);
        $faker->seed(crc32($seed));
        $parsedRule[0] = $this->normalizeRule($parsedRule[0]);
        list($rule, $parameters) = $parsedRule;
        switch ($rule) {
            case 'required':
                $attributeData['required'] = true;
                break;
            case 'accepted':
                $attributeData['required'] = true;
                $attributeData['type'] = 'boolean';
                $attributeData['value'] = true;
                break;
            case 'after':
                $attributeData['type'] = 'date';
                $attributeData['description'][] = Description::parse($rule)->with(date(DATE_RFC850, strtotime($parameters[0])))->getDescription();
                $attributeData['value'] = date(DATE_RFC850, strtotime('+1 day', strtotime($parameters[0])));
                break;
            case 'alpha':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['value'] = $faker->word;
                break;
            case 'alpha_dash':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'alpha_num':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'in':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                $attributeData['value'] = $faker->randomElement($parameters);
                break;
            case 'not_in':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                $attributeData['value'] = $faker->word;
                break;
            case 'min':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                if (Arr::get($attributeData, 'type') === 'numeric' || Arr::get($attributeData, 'type') === 'integer') {
                    $attributeData['value'] = $faker->numberBetween($parameters[0]);
                }
                break;
            case 'max':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                if (Arr::get($attributeData, 'type') === 'numeric' || Arr::get($attributeData, 'type') === 'integer') {
                    $attributeData['value'] = $faker->numberBetween(0, $parameters[0]);
                }
                break;
            case 'between':
                if (!isset($attributeData['type'])) {
                    $attributeData['type'] = 'numeric';
                }
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                $attributeData['value'] = $faker->numberBetween($parameters[0], $parameters[1]);
                break;
            case 'before':
                $attributeData['type'] = 'date';
                $attributeData['description'][] = Description::parse($rule)->with(date(DATE_RFC850, strtotime($parameters[0])))->getDescription();
                $attributeData['value'] = date(DATE_RFC850, strtotime('-1 day', strtotime($parameters[0])));
                break;
            case 'date_format':
                $attributeData['type'] = 'date';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                $attributeData['value'] = date($parameters[0]);
                break;
            case 'different':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'digits':
                $attributeData['type'] = 'numeric';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                $attributeData['value'] = ($parameters[0] < 9) ? $faker->randomNumber($parameters[0], true) : substr(mt_rand(100000000, mt_getrandmax()), 0, $parameters[0]);
                break;
            case 'digits_between':
                $attributeData['type'] = 'numeric';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'file':
                $attributeData['type'] = 'file';
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'image':
                $attributeData['type'] = 'image';
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                break;
            case 'json':
                $attributeData['type'] = 'string';
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['value'] = json_encode(['foo', 'bar', 'baz']);
                break;
            case 'mimetypes':
            case 'mimes':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                break;
            case 'required_if':
                $attributeData['description'][] = Description::parse($rule)->with($this->splitValuePairs($parameters))->getDescription();
                break;
            case 'required_unless':
                $attributeData['description'][] = Description::parse($rule)->with($this->splitValuePairs($parameters))->getDescription();
                break;
            case 'required_with':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                break;
            case 'required_with_all':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' and '))->getDescription();
                break;
            case 'required_without':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                break;
            case 'required_without_all':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' and '))->getDescription();
                break;
            case 'same':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'size':
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'timezone':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['value'] = $faker->timezone;
                break;
            case 'exists':
                $fieldName = isset($parameters[1]) ? $parameters[1] : $ruleName;
                $attributeData['description'][] = Description::parse($rule)->with([Str::singular($parameters[0]), $fieldName])->getDescription();
                break;
            case 'active_url':
                $attributeData['type'] = 'url';
                $attributeData['value'] = $faker->url;
                break;
            case 'regex':
                $attributeData['type'] = 'string';
                $attributeData['description'][] = Description::parse($rule)->with($parameters)->getDescription();
                break;
            case 'boolean':
                $attributeData['value'] = true;
                $attributeData['type'] = $rule;
                break;
            case 'array':
                $attributeData['value'] = $faker->word;
                $attributeData['type'] = $rule;
                break;
            case 'date':
                $attributeData['value'] = $faker->date();
                $attributeData['type'] = $rule;
                break;
            case 'email':
                $attributeData['value'] = $faker->safeEmail;
                $attributeData['type'] = $rule;
                break;
            case 'string':
                $attributeData['value'] = $faker->word;
                $attributeData['type'] = $rule;
                break;
            case 'integer':
                $attributeData['value'] = $faker->randomNumber();
                $attributeData['type'] = $rule;
                break;
            case 'numeric':
                $attributeData['value'] = $faker->randomNumber();
                $attributeData['type'] = $rule;
                break;
            case 'url':
                $attributeData['value'] = $faker->url;
                $attributeData['type'] = $rule;
                break;
            case 'ip':
                $attributeData['value'] = $faker->ipv4;
                $attributeData['type'] = $rule;
                break;
            case 'in_or':
                $attributeData['description'][] = Description::parse($rule)->with($this->fancyImplode($parameters, ', ', ' or '))->getDescription();
                $attributeData['type'] = $rule;
                break;
            case 'range_numeric':
                $attributeData['description'][] = Description::parse($rule)->getDescription();
                $attributeData['type'] = $rule;
                break;
        }
        if (isset($attributeData['value']) && $attributeData['value'] === '') {
            $attributeData['value'] = $faker->word;
        }
        if (!isset($attributeData['type']) || (isset($attributeData['type']) && is_null($attributeData['type']))) {
            $attributeData['type'] = 'string';
        }
    }

    /**
     * Parse a string based rule.
     *
     * @param  string  $rules
     *
     * @return array
     */
    protected function parseStringRule($rules)
    {
        $parameters = [];
        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rules, ':') !== false) {
            list($rules, $parameter) = explode(':', $rules, 2);
            $parameters = $this->parseParameters($rules, $parameter);
        }
        return [strtolower(trim($rules)), $parameters];
    }

    /**
     * Parse a parameter list.
     *
     * @param  string  $rule
     * @param  string  $parameter
     *
     * @return array
     */
    protected function parseParameters($rule, $parameter)
    {
        if (strtolower($rule) === 'regex') {
            return [$parameter];
        }
        return str_getcsv($parameter);
    }

    /**
     * Normalizes a rule so that we can accept short types.
     *
     * @param  string $rule
     *
     * @return string
     */
    protected function normalizeRule($rule)
    {
        switch ($rule) {
            case 'int':
                return 'integer';
            case 'bool':
                return 'boolean';
            default:
                return $rule;
        }
    }

    /**
     * @param  array  $arr
     * @param  string  $first
     * @param  string  $last
     *
     * @return string
     */
    protected function fancyImplode($arr, $first, $last)
    {
        $arr = array_map(function ($value) {
            return '`' . $value . '`';
        }, $arr);
        array_push($arr, implode($last, array_splice($arr, -2)));
        return implode($first, $arr);
    }
}
