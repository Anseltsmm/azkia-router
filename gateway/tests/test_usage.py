from app.usage import conservative_token_estimate, estimate_input_tokens, estimate_response_tokens, normalize_usage


def test_normalize_openai_usage_and_cache_details():
    usage = normalize_usage({"prompt_tokens": 100, "completion_tokens": 20, "total_tokens": 120, "prompt_tokens_details": {"cached_tokens": 40}})
    assert usage is not None
    assert (usage.input_tokens, usage.output_tokens, usage.cached_input_tokens) == (100, 20, 40)


def test_normalize_input_output_schema_and_cache_creation():
    usage = normalize_usage({"input_tokens": "80", "output_tokens": "10", "cache_read_input_tokens": 30, "cache_creation_input_tokens": 5})
    assert usage is not None
    assert (usage.input_tokens, usage.output_tokens, usage.cached_input_tokens, usage.cache_creation_input_tokens) == (80, 10, 30, 5)


def test_normalize_rejects_missing_negative_and_malformed_usage():
    assert normalize_usage(None) is None
    assert normalize_usage({"prompt_tokens": -1, "completion_tokens": "bad"}) is None
    assert normalize_usage({"prompt_tokens": True, "completion_tokens": False}) is None


def test_cached_tokens_are_capped_at_input_tokens():
    usage = normalize_usage({"prompt_tokens": 10, "completion_tokens": 2, "prompt_tokens_details": {"cached_tokens": 50}})
    assert usage is not None
    assert usage.cached_input_tokens == 10


def test_embedding_reservation_has_no_output_tokens():
    input_tokens, output_tokens = conservative_token_estimate({"input": ["hello", "world"]}, "/embeddings", 4096, 32768)
    assert input_tokens > 0
    assert output_tokens == 0


def test_generation_reservation_accounts_for_multiple_choices():
    _, output_tokens = conservative_token_estimate({"messages": [{"role": "user", "content": "hi"}], "max_tokens": 100, "n": 3}, "/chat/completions", 4096, 32768)
    assert output_tokens == 300


def test_completion_reservation_accounts_for_best_of():
    _, output_tokens = conservative_token_estimate({"prompt": "hi", "max_tokens": 100, "n": 2, "best_of": 4}, "/completions", 4096, 32768)
    assert output_tokens == 400


def test_estimators_include_tools_and_structured_output():
    plain = estimate_input_tokens({"messages": [{"role": "user", "content": "hi"}]})
    with_tools = estimate_input_tokens({"messages": [{"role": "user", "content": "hi"}], "tools": [{"type": "function", "function": {"name": "weather", "parameters": {"type": "object", "properties": {"city": {"type": "string"}}}}}]})
    output = estimate_response_tokens({"choices": [{"message": {"tool_calls": [{"function": {"name": "weather", "arguments": "{\"city\":\"Jakarta\"}"}}]}}]})
    assert with_tools > plain
    assert output > 0
