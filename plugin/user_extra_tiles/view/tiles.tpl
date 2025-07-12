{% if user_extra_tiles.fields is not empty %}
<div class="row">
    {% for field in user_extra_tiles.fields %}
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-header">{{ field.name }}</div>
                <div class="card-body">{{ field.value }}</div>
            </div>
        </div>
    {% endfor %}
</div>
{% endif %}
