'use strict';

var gulp = require('gulp');
var gutil = require('gulp-util');
var sass = require('gulp-sass');
var plumber = require('gulp-plumber')
var coffee = require('gulp-coffee');
var concat = require('gulp-concat');
var sourcemaps = require('gulp-sourcemaps');
var uglify = require('gulp-uglify'); 
var minifyCSS = require('gulp-minify-css');
var autoprefixer = require('gulp-autoprefixer');
var rename = require('gulp-rename');

gulp.task('sass', function() {
  return gulp.src('scss/**/*.scss')
    .pipe(sourcemaps.init())
    .pipe(plumber())
    .pipe(sass())
    .pipe(minifyCSS())
    .pipe(concat('events.min.css'))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('./assets'));
});

gulp.task('coffee', function() {
	return gulp.src(['coffee/*.coffee','coffee/**/*.coffee','crafted/src/coffee/**/*.coffee'])
    .pipe(sourcemaps.init())
    .pipe(plumber())
		.pipe(coffee())
    .pipe(uglify())
    .pipe(concat('events.min.js'))
    .pipe(sourcemaps.write('.'))
    .pipe(gulp.dest('./assets'));
})

gulp.task('watch', function() {
  gulp.watch('scss/**/*.scss', ['sass']);
  gulp.watch('coffee/**/*.coffee', ['coffee'])
});

gulp.task('default', ['watch']);