/**
 * Errors
 * @uses Validator
 * @version 1.0.4
 */
Loader.scripts(["validator"]);

window.Errors = {
	'show': function(errors)
	{
		for (var type in errors)
		{
			var params = errors[type];

			for (var j = 0; j < params.length; j++)
				this.parse(type, params[j]);
		}
	},
	'parse': function(type, param)
	{
		switch (type)
		{
			case "require_field":
			{
				switch (typeof param)
				{
					case "string":
						Validator.string(param);
						break;
					case "object":
						Validator.object(param);
						break;
				}
				break;
			}
			case "simple":
			{
				for (var key in param)
					Validator.show(key, param[key]);
				break;
			}
			default:
			{
				Validator.show(type, param);
				break;
			}
		}
	}
};