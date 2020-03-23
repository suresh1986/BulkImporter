<?php
//Config section
$folder_input = 'input';
$folder_output = 'output';
$filename_metadata = 'metadata.json';
$filename_input_csv = 'input.csv';

//Don't change anything below this.
$file_user_metadata = $folder_input . DIRECTORY_SEPARATOR . $filename_metadata;
if( !file_exists( $file_user_metadata ) ) {
	die( 'User metadata file "' . $file_user_metadata . '" does not exist. Make sure there is metadata.json file in input folder.' );
}
$content_metadata = file_get_contents( $file_user_metadata );
if( strlen( $content_metadata ) < 20 ) {
	die( 'Invalid user metadata file "' . $file_user_metadata . '". Make sure its a json file with proper structure. ' );
}
$json_user_meta = json_decode( $content_metadata );
if( empty( $json_user_meta ) ) {
	die( 'Invalid JSON in user metadata file.' );
}
if( empty( $json_user_meta->userData->name ) ) {
	die( 'User name is missing from user metadata file.' );
}
$username = $json_user_meta->userData->name;
$folder_username = $folder_output . DIRECTORY_SEPARATOR . $username;
if( !file_exists( $folder_output ) ) {
	echo 'Making output folder => ' . $folder_output . PHP_EOL;
	if( !mkdir( $folder_output ) ) {
		die( 'Could not create folder "' . $folder_output . '" for output. Make sure the script has write permission to the output folder location.' );
	}
}
if( !file_exists( $folder_username ) ) {
	echo 'Making output folder for user => ' . $folder_username . PHP_EOL;
	if( !mkdir( $folder_username, 0777, true ) ) {
		die( 'Could not create output folder "' . $folder_username . '" for user. Make sure the script has write permission to the output folder.' );
	}
}
echo 'Copying user metadata file from ' . $file_user_metadata . ' to ' . $folder_username . DIRECTORY_SEPARATOR . $filename_metadata . PHP_EOL;
if( !copy( $file_user_metadata, $folder_username . DIRECTORY_SEPARATOR . $filename_metadata ) ) {
	die( 'Could not copy user metadata file to output folder.' );
}
$file_input_csv = $folder_input . DIRECTORY_SEPARATOR . $filename_input_csv;
if( !file_exists( $file_input_csv ) ) {
	die( 'Input csv file does not exist. Check if the path ' . $file_input_csv . ' is correct for input csv.' );
}
$row = 0;
$field_count = 0;
$fh_csv = fopen( $file_input_csv, 'r' );
if( $fh_csv === false ) {
	die( 'Could not open input csv file.' );
}
while ( ( $data = fgetcsv( $fh_csv ) ) !== false ) {
	$row++;
	if( $row == 1 ) {
		$field_count = count( $data );
		if( $field_count < 6 ) {
			die( 'First row should contain 6 required headings. Found only ' . $field_count );
		}
		if( $data[0] != 'TITLE' OR
			$data[1] != 'DESCRIPTION' OR
			$data[2] != 'CATEGORY_NAME' OR
			$data[3] != 'URL_KEY' OR
			$data[4] != 'CATEGORY_DESCRIPTION' OR
			$data[5] != 'FILE_NAME' ) {
			die( 'First row does not contains required headings. First 6 headings should be TITLE, DESCRIPTION, CATEGORY_NAME, URL_KEY, CATEGORY_DESCRIPTION, FILE_NAME in that order.' );
		}
	}
	else {
		if( count( $data ) != $field_count ) {
			continue;
		}
		$title = $data[0];
		$description = $data[1];
		$category_name = $data[2];
		$url_key = $data[3];
		$category_description = $data[4];
		$image_file_name = $data[5];

		$album_metadata = [ 'albumData' => [
			'title' => $category_name,
			'description' => $category_description,
			'access' => 'public'
		] ];
		$album_metadata_json = json_encode( $album_metadata, JSON_PRETTY_PRINT );

		$image_data = [ 'imageData' => [
			'title' => $title,
			'description' => $description,
			'nsfw' => false,
			'category' => [
				'name' => $category_name,
				'urlKey' => $url_key,
				'description' => $category_description
			]
		] ];
		$image_data_json = json_encode( $image_data, JSON_PRETTY_PRINT );

		$album_name_safe = str_replace( [':', '!', '~', '/', '#', '@', '*', '&', '?', '\\'], ['_'], $category_name );
		$album_folder_name = $folder_username . DIRECTORY_SEPARATOR . $album_name_safe;
		if( !file_exists( $album_folder_name ) ) {
			echo 'Making output folder for album => ' . $album_folder_name . PHP_EOL;
			if( !mkdir( $album_folder_name, 0777, true ) ) {
				die( 'Could not create output folder "' . $album_folder_name . '" for album. Check write permission for the output folder.' );
			}
		}
		$album_meta_filename = $album_folder_name . DIRECTORY_SEPARATOR . $filename_metadata;
		echo 'Generating album metadata file => ' .  $album_meta_filename . PHP_EOL;
		if( !file_exists( $album_meta_filename ) ) {
			file_put_contents( $album_meta_filename, $album_metadata_json );
		}

		$image_src_filename = $folder_input . DIRECTORY_SEPARATOR . $image_file_name;
		if( !file_exists( $image_src_filename ) ) {
			continue;
		}
		echo 'Copying image from ' . $image_src_filename . ' to ' . $album_folder_name . DIRECTORY_SEPARATOR . $image_file_name . PHP_EOL;
		copy( $image_src_filename, $album_folder_name . DIRECTORY_SEPARATOR . $image_file_name );
		$image_filename_actual = pathinfo( $image_file_name, PATHINFO_FILENAME );
		$image_data_filename = $album_folder_name . DIRECTORY_SEPARATOR . $image_filename_actual . '.json';
		echo 'Generating image meta data file => ' . $image_data_filename . PHP_EOL;
		if( !file_exists( $image_data_filename ) ) {
			file_put_contents( $image_data_filename, $image_data_json );
		}
	}
}
fclose( $fh_csv );